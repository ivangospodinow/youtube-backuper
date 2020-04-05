# youtube-backuper
Simple script to backup your yourtube chanel

## How to use ?
Clone the repo and setup:
```
git clone https://github.com/ivangospodinow/youtube-backuper.git
cd youtube-backuper
cp config.dist.php config.php
```
Update your values accordingly:
```
<?php
return [
    'channels' => [
        // example:
        // 'PewDiePie' => 'UC-lHJZR3Gqxm24_Vd_AJ5Yw',
    ],
    'youtubeApiKey' => 'YOUR_YOUTUBE_API_KEY',
    'storageDir' => __DIR__ . '/files',
    // height in pixels
    'targetQuality' => 360,
];
```
Start the process:
```
php yb.php
```

Have patience.