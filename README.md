# 본 자료는
[라이믹스](https://github.com/rhymix/rhymix)를 최신버전으로 업데이트 시켜주는 PHP 파일입니다.

# 사용 방법
1. upgrader.php 파일을 루트 디렉토리(index.php가 있는 곳)에 저장해 주세요.
2. 혹시 모르니 백업은 필수!
3. 현재 사용중인 버전과 최신버전을 확인하고 Upgrade 버튼을 눌러주세요!
4. 완료 메시지가 뜨면 끝!

# 원리
<code>./common/constants.php</code> 파일에서 현재 사용중인 버전을 확인합니다. 이후 GitHub API를 이용하여 최신버전 값을 읽은 뒤, 둘을 비교하여 버전이 다른 경우 최신 버전 zip 파일을 다운로드 후 압축 해제 하고 덮어씌웁니다.

# 주의사항
* 항상 실행 전 백업을 합시다!
* 또한 다음 PHP 라이브러리가 설치되어 있어야 합니다.
  - cURL extension (HTTP 요청을 위한 PHP 확장 기능)
  - ZipArchive extension (ZIP 파일 압축 및 해제를 위한 PHP 확장 기능)
* 또한 디렉토리에 쓰기 권한이 있어야 합니다.

# 라이선스
MIT 라이선스, GPL v3 둘 중 원하는 라이선스를 선택하세요! 저는 MIT를 권장합니다.