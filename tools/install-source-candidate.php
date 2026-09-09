<?php
declare(strict_types=1);
$root=dirname(__DIR__);
if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--no-dev')) {
 throw new RuntimeException('Usage: install-source-candidate.php [--no-dev]');
}
$production = ($argv[1] ?? null) === '--no-dev';
$manifest=json_decode(file_get_contents($root.'/composer.json'),true,flags:JSON_THROW_ON_ERROR);
$dependencies=json_decode(file_get_contents($root.'/resources/source-ci-dependencies.json'),true,flags:JSON_THROW_ON_ERROR);
$snapshotPath=$root.'/../candidate-dependency-evidence.json';
$lockPath=$root.'/../candidate-composer.lock';
$inputs=[
 'sdk_commit'=>trim(shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD')??''),
 'manifest_sha256'=>hash_file('sha256',$root.'/composer.json'),
 'selection_sha256'=>hash_file('sha256',$root.'/resources/source-ci-dependencies.json'),
 'run_id'=>getenv('GITHUB_RUN_ID'),'run_attempt'=>getenv('GITHUB_RUN_ATTEMPT'),'job'=>getenv('GITHUB_JOB'),
];
$jobScoped=is_string($inputs['run_id'])&&$inputs['run_id']!==''
 &&is_string($inputs['run_attempt'])&&$inputs['run_attempt']!==''
 &&is_string($inputs['job'])&&$inputs['job']!=='';
$snapshot=$production&&$jobScoped&&is_file($snapshotPath)
 ? json_decode(file_get_contents($snapshotPath),true,flags:JSON_THROW_ON_ERROR) : null;
if($snapshot!==null&&(($snapshot['source_inputs']??null)!==$inputs
 ||($snapshot['install_succeeded']??null)!==true||($snapshot['release_attestation']??null)!==false
 ||array_keys($snapshot['dependencies']??[])!==array_keys($dependencies)))
 throw new RuntimeException('Source installation snapshot does not match this job and SDK graph.');
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
 $tree=trim(shell_exec('git -C '.escapeshellarg($path).' rev-parse HEAD^{tree}')??'');
 $clean=proc_open(['git','-C',$path,'diff','--quiet','HEAD','--'],[STDIN,STDOUT,STDERR],$cleanPipes);
 if(!is_resource($clean)||proc_close($clean)!==0)
  throw new RuntimeException('Candidate dependency checkout has changed: '.$name);
 $untracked=trim(shell_exec('git -C '.escapeshellarg($path).' ls-files --others')??'');
 if($untracked!=='')throw new RuntimeException('Candidate dependency checkout contains untracked files: '.$name);
 $identity=['version'=>$version,'checkout_commit'=>$observed,'checkout_tree'=>$tree,
  'composer_sha256'=>hash_file('sha256',$path.'/composer.json'),'remote_coordinate_verified'=>true];
 if($snapshot!==null&&($snapshot['dependencies'][$name]??null)!==$identity)
  throw new RuntimeException('Candidate dependency differs from the installed snapshot: '.$name);
 // Reuse only a previously verified development coordinate in this same job. The frozen checkout
 // survives a maintainer deleting its branch between source tests and the production-only install.
 // Published version tags still undergo their live identity check on every invocation.
 if($snapshot===null||!str_starts_with($version,'dev-')){
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
 }
 $config['repositories'][]=['type'=>'path','url'=>$path,'options'=>['symlink'=>false,'versions'=>[$name=>$dependency['version']]]];
 $source[$name]=['path'=>$path,'version'=>$dependency['version']];
 $evidence[$name]=$identity;
}
$hasDevelopmentSelection=false;
foreach($dependencies as $dependency){
 if(str_starts_with($dependency['version'],'dev-'))$hasDevelopmentSelection=true;
}
$config['minimum-stability']=$hasDevelopmentSelection?'dev':($manifest['minimum-stability']??'stable');
$config['prefer-stable']=true;
$plan=json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$temporary=$root.'/.composer.candidate.json';$temporaryLock=$root.'/.composer.candidate.lock';
if($snapshot!==null&&(($snapshot['composer_plan_sha256']??null)!==hash('sha256',$plan)
 ||!is_file($lockPath)||($snapshot['composer_lock_sha256']??null)!==hash_file('sha256',$lockPath)))
 throw new RuntimeException('The verified Composer plan or lock has changed.');
file_put_contents($temporary,$plan);
if($snapshot!==null&&!copy($lockPath,$temporaryLock))throw new RuntimeException('Cannot restore the verified Composer lock.');
$env=getenv();$env['COMPOSER']=$temporary;$env['COMPOSER_ROOT_VERSION']='dev-candidate';
$command=['composer','install','--no-scripts','--no-plugins','--no-interaction','--prefer-dist'];
if($production){$command[]='--no-dev';$command[]='--classmap-authoritative';}
$process=proc_open($command,[STDIN,STDOUT,STDERR],$pipes,$root,$env);
$status=is_resource($process)?proc_close($process):1;
unlink($temporary);
if($status!==0){@unlink($temporaryLock);exit($status);}
if(!is_file($temporaryLock)||!copy($temporaryLock,$lockPath))throw new RuntimeException('Cannot preserve the successful Composer lock.');
unlink($temporaryLock);
$consumer=['repositories'=>$config['repositories'],'require'=>array_intersect_key($config['require'],$dependencies),
 'minimum-stability'=>$config['minimum-stability']];
$consumerPath=$root.'/../candidate-consumer.json';file_put_contents($consumerPath,json_encode($consumer,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
file_put_contents($snapshotPath,json_encode(['release_attestation'=>false,'install_succeeded'=>true,
 'source_inputs'=>$inputs,'composer_plan_sha256'=>hash('sha256',$plan),
 'composer_lock_sha256'=>hash_file('sha256',$lockPath),'dependencies'=>$evidence],
 JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
$environment=getenv('GITHUB_ENV');if($environment){file_put_contents($environment,'KUMWE_SOURCE_CONSUMER_CONFIG='.$consumerPath."\n".'KUMWE_SOURCE_DEPENDENCIES='.json_encode($source,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n",FILE_APPEND);}
