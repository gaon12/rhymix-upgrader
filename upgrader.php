<?php
require_once './config/config.inc.php';
$oContext = &Context::getInstance();
$oContext->init();
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
    @mkdir($dst, 0777, true);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recurse_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                if (!@copy($src . '/' . $file, $dst . '/' . $file)) {
                    $error = error_get_last();
                    throw new Exception('복사 실패: ' . $error['message']);
                }
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
    $options = ['http' => ['header' => ['User-Agent: PHP-test']]];
    $context = stream_context_create($options);
    $response = @file_get_contents($api_url, false, $context);
    if ($response === false) {
        return "ERR: 500";
    }
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
    $content = @file_get_contents($url);
    if ($content === false) {
        return "ERR: 300";
    }
    file_put_contents($path, $content);
    return true;
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
    set_time_limit(180);
    $result = [];

    if (!checkDirectoryWritable('.')) {
        $result['status'] = 'error';
        $result['message'] = 'ERR: 200';
        return $result;
    }

    $zip_path = './rhymix-' . $latest_version . '.zip';
    $download_link = 'https://github.com/rhymix/rhymix/archive/refs/tags/' . $latest_version . '.zip';

    if (checkGitFolderExists()) {
        $download_link = 'https://github.com/rhymix/rhymix/archive/develop.zip';
    }

    $download_result = downloadFile($download_link, $zip_path);

    if ($download_result !== true) {
        $result['status'] = 'error';
        $result['message'] = $download_result;
        return $result;
    }

    $src = './rhymix-' . $latest_version;
    $dest = '.';
    $zip = new ZipArchive;
    $zip_status = $zip->open($zip_path);

    if ($zip_status === TRUE) {
        $zip->extractTo($src);
        $zip->close();

        try {
            recurse_copy($src, $dest);
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
            return $result;
        }
    } else {
        $result['status'] = 'error';
        $result['message'] = 'ERR: 400';
        return $result;
    }

    $unzip_folder = './rhymix-' . $latest_version;
    $zip->extractTo($unzip_folder);
    $zip->close();

    if (is_dir($unzip_folder)) {
        recurse_copy($unzip_folder, '.');
        rrmdir($unzip_folder);
    } else {
        $result['status'] = 'error';
        $result['message'] = 'ERR: 410';
        return $result;
    }

    unlink($zip_path);
    $result['status'] = 'success';
    $result['message'] = 'Upgrade completed successfully!';
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if ($is_admin) {
        $latest_version = getLatestVersion();
        $result = upgradeVersion($latest_version);
        echo json_encode($result);
    } else {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'ERR: 100']);
    }
    exit;
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <title>Rhymix Updater</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.4/dist/sweetalert2.all.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .updater-container {
            max-width: 500px;
            margin: 100px auto;
            background-color: #ffffff;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 28px;
            margin-bottom: 30px;
        }

        #upgrade-btn {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="updater-container">
        <h1 class="text-center">Rhymix Updater</h1>
        <div class="mb-3">
            <p>Current version: <span id="current-version"></span></p>
            <p>Latest version: <span id="latest-version"></span></p>
        </div>
        <button class="btn btn-primary mb-3" id="upgrade-btn">Upgrade to the latest version</button>
        <div class="progress" id="progress-bar-container" style="display: none;">
            <div class="progress-bar" id="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>
    <script>
        $(document).ready(function () {
    const is_admin = <?= json_encode($is_admin) ?>;
    
    $('#current-version').text(getCurrentVersion());
    getLatestVersion().then(function (latest_version) {
        $('#latest-version').text(latest_version);
    });

    $('#upgrade-btn').on('click', function () {
        if (!is_admin) {
            Swal.fire({
                title: 'Error!',
                text: 'ERR: 100',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
    
        Swal.fire({
            title: 'Are you sure?',
            text: "This will upgrade Rhymix to the latest version. Make sure you have a backup of your files and database.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, upgrade!'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#progress-bar-container').show(); // Progress bar를 보이게 합니다.
                upgradeVersion().then(function (response) {
                    if (response.status === 'success') {
                        updateProgressBar(100);
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    });
});

function getCurrentVersion() {
    return '<?=getCurrentVersion()?>';
}

async function getLatestVersion() {
    const response = await fetch('https://api.github.com/repos/rhymix/rhymix/tags', {
        headers: { 'User-Agent': 'PHP-test' }
    });
    const tags = await response.json();
    return tags[0].name;
}

function updateProgressBar(percentage) {
    $('#progress-bar').css('width', percentage + '%').attr('aria-valuenow', percentage).text(percentage + '%');
}

async function upgradeVersion() {
    updateProgressBar(25); // 파일 다운로드 시작

    const response = await fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ action: 'upgrade' })
    });

    updateProgressBar(50); // 파일 다운로드 완료

    if (response.ok) {
        const result = await response.json();
        if (result.status === 'success') {
            updateProgressBar(75); // 압축 해제 완료

            // 덮어쓰기를 위한 코드 위치
            const copyResult = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'copy' })
            });

            if (copyResult.ok) {
                updateProgressBar(85); // 덮어쓰기 완료
            } else {
                const error = await copyResult.json();
                return {
                    status: 'error',
                    message: error.message
                };
            }

            // zip 파일 및 압축 해제한 폴더 및 파일 삭제 코드 위치
            //...
            const cleanupResult = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'cleanup' })
            });

            if (cleanupResult.ok) {
                updateProgressBar(95); // zip 파일 및 압축 해제한 폴더 및 파일 삭제 완료
            } else {
                const error = await cleanupResult.json();
                return {
                    status: 'error',
                    message: error.message
                };
            }

            return result;
        } else {
            return {
                status: 'error',
                message: result.message
            };
        }
    } else {
        const error = await response.json(); // 에러 메시지를 받아옴
        return {
            status: 'error',
            message: error.message // 받아온 에러 메시지를 반환하도록 수정
        };
    }
}

    </script>
</body>
</html>
