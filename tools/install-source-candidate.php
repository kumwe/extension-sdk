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
 $config['repositories'][]=['type'=>'path','url'=>$path,'options'=>['symlink'=>false,'versions'=>[$name=>$dependency['version']]]];
 $source[$name]=['path'=>$path,'version'=>$dependency['version']];
 $evidence[$name]=['version'=>$dependency['version'],'checkout_commit'=>$observed];
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
