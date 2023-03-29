<?php

// Rhymix 로딩
require_once './config/config.inc.php';
$oContext = &Context::getInstance();
$oContext->init();

// 관리자 권한 확인
$is_admin = false;
$logged_info = Context::get('logged_info');
if ($logged_info && $logged_info->is_admin == 'Y') {
    $is_admin = true;
}

function rrmdir($src)
{
    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            $full = $src . '/' . $file;
            if (is_dir($full)) {
                rrmdir($full);
            } else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
}

function recurse_copy($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recurse_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

function getCurrentVersion()
{
    $current_version = '';
    $file = file_get_contents('./common/constants.php');
    preg_match("/define\('RX_VERSION', '([^']+)'\);/", $file, $matches);
    if (!empty($matches[1])) {
        $current_version = $matches[1];
    }
    return $current_version;
}

function getLatestVersion()
{
    $latest_version = '';
    $api_url = 'https://api.github.com/repos/rhymix/rhymix/tags';
    $options = ['http' => ['header' => ['User-Agent: PHP']]];
    $context = stream_context_create($options);
    $response = file_get_contents($api_url, false, $context);
    $tags = json_decode($response);

    if (!empty($tags[0]->name)) {
        $latest_version = $tags[0]->name;
    }
    return $latest_version;
}

function checkGitFolderExists()
{
    return is_dir('.git');
}

function downloadFile($url, $path)
{
    $content = file_get_contents($url);
    file_put_contents($path, $content);
}

function checkDirectoryWritable($src)
{
    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            $full = $src . '/' . $file;
            if (is_dir($full)) {
                if (!is_writable($full) || !checkDirectoryWritable($full)) {
                    return false;
                }
            } else {
                if (!is_writable($full)) {
                    return false;
                }
            }
        }
    }
    closedir($dir);
    return true;
}

function upgradeVersion($latest_version)
{
    set_time_limit(180); // 타임아웃 시간을 180초(3분)으로 설정

    $result = [];

    // Check if directories are writable
    if (!checkDirectoryWritable('.')) {
        $result['status'] = 'error';
        $result['message'] = '디렉터리에 쓰기 권한이 없습니다. 권한을 확인해주세요.';
        return $result;
    }

    // Download the latest version
    $download_url = "https://github.com/rhymix/rhymix/archive/refs/tags/{$latest_version}.zip";
    $zip_path = "{$latest_version}.zip";
    downloadFile($download_url, $zip_path);

    // Unzip the archive
    $zip = new ZipArchive;
    $zip->open($zip_path);
    $zip->extractTo('.');
    $zip->close();

    // Copy files from the extracted folder
    $src = "rhymix-{$latest_version}";
    $dest = '.';
    recurse_copy($src, $dest);

    // Remove the zip file and extracted folder
    unlink($zip_path);
    rrmdir($src);

    $result['status'] = 'success';
    $result['message'] = 'Upgrade Complete';
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 관리자 권한 확인 후 처리
    if ($is_admin) {
        $latest_version = getLatestVersion();
        $result = upgradeVersion($latest_version);
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        header('HTTP/1.1 403 Forbidden');
        // 에러 메시지를 삭제하고 빈 응답 본문을 반환합니다.
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rhymix Upgrader</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css"
        integrity="sha512-NhSC1YmyruXifcj/KFRWoC561YpHpc5Jtzgvbuzx5VozKpWvQ+4nXhPdFgmx8xqexRcpAglTj9sIBWINXa8x5w=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f7f7f7;
        }

        .container {
            padding: 2rem;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            width: 500px;
            height: 300px;
        }

        .title {
            text-align: center;
        }

        li {
            font-family: Arial, sans-serif;
        }

        button {
            background-color: #007bff;
            border: none;
            color: #fff;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            border-radius: 5px;
            cursor: pointer;
            margin: auto;
            display: block;
        }

        .progress {
            display: none;
            margin-top: 1rem;
        }

        .progress-bar {
            background-color: #007bff;
            height: 1rem;
            width: 0;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1 class="title">Rhymix Upgrader</h1>
        <ul>
            <li>Current version: <?= getCurrentVersion(); ?></li>
            <li>Latest version: <?= getLatestVersion(); ?></li>
            <li>.git Found: <?= checkGitFolderExists() ? 'true' : 'false' ?></li>
        </ul>
        <br><br>
        <button type="button" onclick="checkVersionsAndUpgrade()">Upgrade</button>
        <div class="progress">
            <div class="progress-bar"></div>
        </div>
        <p id="upgradeStatus"></p>
    </div>
    <script>
        function checkVersionsAndUpgrade() {
        const currentVersion = '<?= getCurrentVersion(); ?>';
        const latestVersion = '<?= getLatestVersion(); ?>';
        const gitFolderExists = <?= checkGitFolderExists() ? 'true' : 'false' ?>;

        if (currentVersion === latestVersion) {
            const shouldUpgrade = confirm('현재 최신버전을 사용중인 것 같습니다. 그래도 최신 버전으로 덮어씌우시겠습니까?');
            if (!shouldUpgrade) {
                return;
            }
        }

        if (gitFolderExists) {
            const shouldUpgradeWithGit = confirm('.git 폴더를 발견했습니다. git 명령어로 업데이트 하는 것을 권장합니다. 그래도 업데이트 하시겠습니까?');
            if (!shouldUpgradeWithGit) {
                return;
            }
        }

            const upgradeBtn = document.querySelector('button');
            upgradeBtn.disabled = true;
            document.querySelector('.progress').style.display = 'block';

            fetch('', { method: 'POST' })
                .then(response => {
                    if (response.status === 403) {
                        throw new Error('에러: 관리자 권한이 필요합니다.');
                    }
                    return response.json();
                })
                .then(result => {
                    document.getElementById('upgradeStatus').innerText = result.message;

                    if (result.status === 'success') {
                        document.querySelector('.progress-bar').style.width = '100%';
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        upgradeBtn.disabled = false;
                        document.querySelector('.progress').style.display = 'none';
                    }
                })
                .catch(error => {
                    document.getElementById('upgradeStatus').innerText = error.message;
                    upgradeBtn.disabled = false;
                    document.querySelector('.progress').style.display = 'none';
                });
        }
    </script>
</body>

</html>