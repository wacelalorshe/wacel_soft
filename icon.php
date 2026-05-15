<?php
// create_icon.php - لتوليد أيقونة بسيطة
header('Content-Type: image/png');
$size = isset($_GET['size']) ? (int)$_GET['size'] : 192;
$img = imagecreatetruecolor($size, $size);
$bg = imagecolorallocate($img, 108, 92, 231); // #6c5ce7
$textColor = imagecolorallocate($img, 255, 255, 255);
imagefill($img, 0, 0, $bg);
$text = '💳';
$fontSize = $size * 0.5;
imagestring($img, 5, $size/4, $size/3, $text, $textColor);
imagepng($img);
imagedestroy($img);