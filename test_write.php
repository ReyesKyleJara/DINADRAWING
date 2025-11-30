<?php
$dir = __DIR__.'/Assets/uploads/banners';
$file = $dir.'/perm_test.txt';
echo "Dir: $dir<br>";
echo "is_dir=".(int)is_dir($dir)." writable=".(int)is_writable($dir)."<br>";
if (is_writable($dir)){
  if (file_put_contents($file,'ok '.time())) echo "Wrote file: ".basename($file);
  else echo "Write failed";
} else echo "Not writable";
?>