<?php
// Zips /ext (the mounted extension) into /tmp/ext.zip, skipping docker-logs/ and tests/.
$zip = new ZipArchive();
$zip->open('/tmp/ext.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
$base = '/ext';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1));
    if (preg_match('#^(docker-logs|tests)(/|$)#', $rel)) {
        continue;
    }
    $zip->addFile($f->getPathname(), $rel);
}
$zip->close();
echo "ext.zip created\n";
