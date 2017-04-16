<?php

namespace Merchant;

class Base
{

    /**
     * Version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Status starting.
     *
     * @var int
     */
    const STATUS_STARTING = 1;

    /**
     * Status running.
     *
     * @var int
     */
    const STATUS_RUNNING = 2;

    /**
     * Status shutdown.
     *
     * @var int
     */
    const STATUS_SHUTDOWN = 4;
    /**
     * Status reloading.
     *
     * @var int
     */
    const STATUS_RELOADING = 8;
    /**
     * Number of worker processes.
     *
     * @var int
     */
    public $count = 1;

    /**
     * Unix user of processes, needs appropriate privileges (usually root).
     *
     * @var string
     */
    public $user = '';
    /**
     * Unix group of processes, needs appropriate privileges (usually root).
     *
     * @var string
     */
    public $group = '';
    /**
     * Daemonize.
     *
     * @var bool
     */
    public static $daemonize = false;

    /**
     * Current status.
     *
     * @var int
     */
    protected static $status = 0;

    protected $worker_id = null;
    /**
     * All worker instances.
     *
     * @var array
     */
    protected static $workers = [];
    /**
     * The PID of master process.
     *
     * @var int
     */
    protected static $master_pid = 0;

    /**
     * All worker porcesses pid.
     * The format is like this [worker_id=>[pid=>pid, pid=>pid, ..], ..]
     *
     * @var array
     */
    protected static $pid_map = [];

    /**
     * Start file.
     *
     * @var string
     */
    protected static $start_file = '';

    /**
     * The file to store master process PID.
     *
     * @var string
     */
    public static $pid_file = '';

    /**
     * Log file.
     *
     * @var mixed
     */
    public static $log_file = '';

    /**
     * Stdout file.
     *
     * @var string
     */
    public static $stdoutFile = '/dev/null';


    public function __construct()
    {

    }

    /**
     * 检测运行环境
     */
    protected function checkSapiEnv()
    {
        if (php_sapi_name() != "cli") {
            exit("only run in command line mode.\n");
        }

        if (PHP_VERSION < '5.6.0') {
            exit("PHP version cannot be less than 5.6.\n");
        }

        if (!function_exists('pcntl_signal')) {
            exit("PHP does not appear to be compiled with the PCNTL extension.\n");
        }
    }

    /**
     * 设置进程标题
     *
     * @param $title
     */
    public function setProcessTitle($title)
    {
        cli_set_process_title($title);
    }

    public function displayUI()
    {

    }

    public function log($message)
    {
        $message .= "\n";

        file_put_contents(self::$log_file, ' PID:' . posix_getpid() . ' ' . $message .' time : '.date("Y-m-d H:i:s") .  "\n", FILE_APPEND | LOCK_EX);
    }

    public function fatalError()
    {
        $errors = error_get_last();
        $error_msg = "WORKER EXIT UNEXPECTED ";
        if ($errors && ($errors['type'] === E_ERROR ||
                $errors['type'] === E_PARSE ||
                $errors['type'] === E_CORE_ERROR ||
                $errors['type'] === E_COMPILE_ERROR ||
                $errors['type'] === E_RECOVERABLE_ERROR)
        ) {
            $error_msg .= $this->getErrorType($errors['type']) . " {$errors['message']} in {$errors['file']} on line {$errors['line']}";
        }

        $this->log($error_msg);

        exit($errors['message']);
    }

    /**
     * Get error message by error code.
     *
     * @param integer $type
     * @return string
     */
    protected static function getErrorType($type)
    {
        switch ($type) {
            case E_ERROR: // 1 //
                return 'E_ERROR';
            case E_WARNING: // 2 //
                return 'E_WARNING';
            case E_PARSE: // 4 //
                return 'E_PARSE';
            case E_NOTICE: // 8 //
                return 'E_NOTICE';
            case E_CORE_ERROR: // 16 //
                return 'E_CORE_ERROR';
            case E_CORE_WARNING: // 32 //
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: // 64 //
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: // 128 //
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR: // 256 //
                return 'E_USER_ERROR';
            case E_USER_WARNING: // 512 //
                return 'E_USER_WARNING';
            case E_USER_NOTICE: // 1024 //
                return 'E_USER_NOTICE';
            case E_STRICT: // 2048 //
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR: // 4096 //
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: // 8192 //
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED: // 16384 //
                return 'E_USER_DEPRECATED';
        }
        return "";
    }

    /**
     * 以守护进程方式运行
     *
     * @throws \Exception
     */
    public function daemonize()
    {
        if (self::$daemonize === false) {
            return;
        }

        umask(0);//修改进程用户掩码

        $pid = pcntl_fork();

        if (-1 === $pid) {
            throw new \Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }

        if (-1 === posix_setsid()) {
            throw new \Exception('setsid fail');
        }

        $pid = pcntl_fork();

        if (-1 === $pid) {
            throw new \Exception("fork fail");
        } elseif ($pid > 0) {
            exit(0);
        }
    }
}