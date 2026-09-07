<?php
declare(strict_types=1);
$root=dirname(__DIR__);
if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--no-dev')) {
 throw new RuntimeException('Usage: install-source-candidate.php [--no-dev]');
}
$production = ($argv[1] ?? null) === '--no-dev';
$manifest=json_decode(file_get_contents($root.'/composer.json'),true,flags:JSON_THROW_ON_ERROR);
$dependencies=json_decode(file_get_contents($root.'/resources/source-ci-dependencies.json'),true,flags:JSON_THROW_ON_ERROR);
$config=$manifest;$config['repositories']=[];
$source=[];$evidence=[];
foreach($dependencies as $name=>$dependency){
 $path=realpath($root.'/../dependencies/'.substr($name,6));
 if($path===false)throw new RuntimeException('Missing candidate dependency '.$name);
 $metadata=json_decode(file_get_contents($path.'/composer.json'),true,flags:JSON_THROW_ON_ERROR);
 if($metadata['name']!==$name)throw new RuntimeException('Candidate dependency identity mismatch.');
 $observed=trim(shell_exec('git -C '.escapeshellarg($path).' rev-parse HEAD')??'');
 if(!is_string($dependency['ref'])||preg_match('/^[0-9a-f]{40}$/D',$dependency['ref'])!==1
  ||$observed!==$dependency['ref'])throw new RuntimeException('Candidate dependency commit mismatch: '.$name);
 // Verify the advertised Composer coordinate against the real remote before assigning it to a checkout.
 $version=$dependency['version'];
 if(!is_string($version))throw new RuntimeException('Invalid candidate version.');
 $refs=str_starts_with($version,'dev-')
  ? ['refs/heads/'.substr($version,4)]
  : ['refs/tags/'.$version,'refs/tags/'.$version.'^{}','refs/tags/v'.$version,'refs/tags/v'.$version.'^{}'];
 $remote=proc_open(['git','ls-remote','--exit-code','https://github.com/'.$name.'.git',...$refs],
  [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($remote))throw new RuntimeException('Cannot verify source coordinate: '.$name);
 fclose($pipes[0]);$remoteOutput=stream_get_contents($pipes[1]);fclose($pipes[1]);
 stream_get_contents($pipes[2]);fclose($pipes[2]);$remoteStatus=proc_close($remote);
 $resolved=[];
 foreach(explode("\n",trim($remoteOutput)) as $line){
  if(preg_match('/^([0-9a-f]{40})\s+(refs\/[^\s]+)$/D',$line,$match)===1)$resolved[$match[2]]=$match[1];
 }
 $matches=false;
 foreach($refs as $ref){
  if(str_ends_with($ref,'^{}'))continue;
  if(($resolved[$ref.'^{}']??$resolved[$ref]??null)===$observed)$matches=true;
 }
 if($remoteStatus===0&&!$matches&&str_starts_with($version,'dev-')&&isset($resolved[$refs[0]])){
  // A reviewed commit remains valid when its live development branch advances.
  $remoteHead=$resolved[$refs[0]];
  $shallow=trim(shell_exec('git -C '.escapeshellarg($path).' rev-parse --is-shallow-repository')??'')==='true';
  $fetch=['git','-C',$path,'fetch','--quiet','--no-tags'];
  if($shallow)$fetch[]='--unshallow';
  $fetch[]='https://github.com/'.$name.'.git';$fetch[]=$refs[0];
  $fetchProcess=proc_open($fetch,[STDIN,STDOUT,STDERR],$fetchPipes);
  $fetchStatus=is_resource($fetchProcess)?proc_close($fetchProcess):1;
  if($fetchStatus===0){
   $ancestry=proc_open(['git','-C',$path,'merge-base','--is-ancestor',$observed,$remoteHead],
    [STDIN,STDOUT,STDERR],$ancestryPipes);
   $matches=is_resource($ancestry)&&proc_close($ancestry)===0;
  }
 }
 if($remoteStatus!==0||!$matches)throw new RuntimeException('Unavailable or unrelated source coordinate: '.$name.' '.$version);
 $config['repositories'][]=['type'=>'path','url'=>$path,'options'=>['symlink'=>false,'versions'=>[$name=>$dependency['version']]]];
 $source[$name]=['path'=>$path,'version'=>$dependency['version']];
 $evidence[$name]=['version'=>$dependency['version'],'checkout_commit'=>$observed,'remote_coordinate_verified'=>true];
}
$config['minimum-stability']='dev';$config['prefer-stable']=true;
$temporary=$root.'/.composer.candidate.json';file_put_contents($temporary,json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
$env=getenv();$env['COMPOSER']=$temporary;$env['COMPOSER_ROOT_VERSION']='dev-candidate';
$command=['composer','install','--no-scripts','--no-plugins','--no-interaction','--prefer-dist'];
if($production){$command[]='--no-dev';$command[]='--classmap-authoritative';}
$process=proc_open($command,[STDIN,STDOUT,STDERR],$pipes,$root,$env);
$status=is_resource($process)?proc_close($process):1;
unlink($temporary);@unlink($root.'/.composer.candidate.lock');if($status!==0)exit($status);
$consumer=['repositories'=>$config['repositories'],'require'=>array_intersect_key($config['require'],$dependencies)];
$consumerPath=$root.'/../candidate-consumer.json';file_put_contents($consumerPath,json_encode($consumer,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
file_put_contents($root.'/../candidate-dependency-evidence.json',json_encode(['release_attestation'=>false,'dependencies'=>$evidence],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
$environment=getenv('GITHUB_ENV');if($environment){file_put_contents($environment,'KUMWE_SOURCE_CONSUMER_CONFIG='.$consumerPath."\n".'KUMWE_SOURCE_DEPENDENCIES='.json_encode($source,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n",FILE_APPEND);}
