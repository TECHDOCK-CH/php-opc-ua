{ pkgs, lib, config, inputs, ... }:

{
  # https://devenv.sh/basics/
  env.OPCUA_TEST_SERVER_URL = "opc.tcp://opcua.demo-this.com:62544/Quickstarts/AlarmConditionServer";

  # https://devenv.sh/packages/
  packages = [
    pkgs.git
  ];

  # https://devenv.sh/languages/
  languages.php = {
    enable = true;
    version = "8.4";
    extensions = [ "sockets" "mbstring" "openssl" "zlib" "pcov" "xdebug" ];
    ini = ''
      memory_limit = 256M
      display_errors = On
      error_reporting = E_ALL
      xdebug.mode = coverage
    '';
  };

  # https://devenv.sh/scripts/
  scripts = {
    # Composer shortcuts
    test.exec = "composer test";
    analyse.exec = "composer analyse";
    cs-check.exec = "composer cs-check";
    cs-fix.exec = "composer cs-fix";

    # Run all checks
    check-all.exec = ''
      set -e
      echo "Running code style check..."
      composer cs-check
      echo "Running static analysis..."
      composer analyse
      echo "Running tests..."
      composer test
      echo "✅ All checks passed!"
    '';
  };

  enterShell = ''
    echo "🚀 PHP OPC UA Development Environment"
    echo ""
    php --version
    composer --version 2>/dev/null || echo "Run 'composer install' to set up dependencies"
    echo ""
    echo "Available commands:"
    echo "  test       - Run PHPUnit tests"
    echo "  analyse    - Run PHPStan analysis"
    echo "  cs-check   - Check code style"
    echo "  cs-fix     - Fix code style"
    echo "  check-all  - Run all checks"
    echo ""
  '';

  # https://devenv.sh/tests/
  enterTest = ''
    echo "Running devenv tests..."
    php --version | grep "PHP 8.4"
    php -m | grep sockets
  '';

  # https://devenv.sh/git-hooks/
  git-hooks.hooks = {
    # Run checks before commit
    pre-commit = {
      enable = false;  # Enable manually with: devenv gc enable pre-commit
      stages = [ "pre-commit" ];
      entry = "check-all";
      pass_filenames = false;
    };
  };

  # https://devenv.sh/reference/options/
}
