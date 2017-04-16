<?php

namespace Merchant;

class Process extends Base
{
    /**
     * 进程重新载入标识
     *
     * @var bool
     */
    static $reloading = false;

    /**
     * 回调函数
     *
     * @var null
     */
    public $onCallback = null;

    /**
     * worker进程最大执行次数
     *
     * @var int
     */
    public $maximumTimes = 5;

    /**
     * worker进程已执行的测试
     *
     * @var int
     */
    public $workerRunTime = 0;


    public function __construct()
    {
        //$this->worker_id = spl_object_hash($this);

        //self::$workers[$this->worker_id] = $this;

        $this->onCallback = function ($param) {
        };
    }

    /**
     * 开启守护进程
     */
    public function runAll()
    {
        $this->checkSapiEnv();
        $this->init();
        if (!self::$reloading) {
            $this->parseCommand();
        }
        $this->daemonize();
        $this->installSignal();
        $this->saveMasterPid();
        $this->forkWorks();
        $this->displayUI();
        $this->resetStd();
        $this->monitorWorkers();
    }

    /**
     * 初始化进程,生成主进程ID文件，日志文件
     */
    protected function init()
    {
        $backtrace = debug_backtrace();

        self::$start_file = trim($backtrace[count($backtrace) - 1]['file'], '.php');

        //进程文件
        if (empty(self::$pid_file)) {
            self::$pid_file = __DIR__ . '/../Daemon.pid';
        }

        if (empty(self::$log_file)) {
            self::$log_file = __DIR__ . '/merchant_daemon/' . str_replace('/', '_', self::$start_file) . '.log';
        }

        if (!is_file(self::$pid_file)) {
            touch(self::$pid_file);
            chmod(self::$pid_file, 0622);
        }

        if (!is_file(self::$log_file)) {
            touch(self::$log_file);
            chmod(self::$log_file, 0622);
        }
        //
        self::$status = self::STATUS_STARTING;

        $this->setProcessTitle("master process start_file" . self::$start_file);
    }

    /**
     * 解析命令行参数
     */
    public function parseCommand()
    {
        global $argv;

        $startFile = $argv[0];

        if (empty($startFile)) {
            exit("Usage: php yourfile.php {start|stop|restart|reload|status}\n");
        }

        $command = trim($argv[1]);
        $command2 = empty($argv[2]) ? '' : trim($argv[2]);

        $master_pid = file_get_contents(self::$pid_file);

        $master_is_active = $master_pid && posix_kill($master_pid, 0);

        if ($master_is_active) {
            if ($command == 'start') {
                $this->log(self::$start_file . ' already running');
                exit;
            }
        } elseif ($command != 'start') {
            $this->log('Usage: php yourfile.php {start}');
            exit;
        }

        switch ($command) {
            case 'start':
                if ($command2 === '--Daemon') {
                    self::$daemonize = true;
                }

                break;
            case 'reload':
                $this->log(self::$start_file . ' is reloading');
                posix_kill($master_pid, SIGUSR1);

                exit(0);
            case 'stop':
                $this->log(self::$start_file . ' is stoping...');
                posix_kill($master_pid, SIGINT);

                exit(0);
            case 'status':
                posix_kill($master_pid, SIGUSR2);
                $this->log(self::$start_file . 'show statistics');
                break;
            default:
                exit("Usage: php yourfile.php {start|stop|restart|reload|status}\n");
        }
    }

    /**
     * 安装主进程信号
     */
    public function installSignal()
    {
        //stop
        pcntl_signal(SIGINT, [$this, 'signalHandle'], false);
        //reload
        pcntl_signal(SIGUSR1, [$this, 'signalHandle'], false);
        //status
        pcntl_signal(SIGUSR2, [$this, 'signalHandle'], false);
        //child exit
        //pcntl_signal(SIGCHLD, [$this, 'signalHandle'], false);
        //ignore
        pcntl_signal(SIGPIPE, SIG_IGN, false);
    }

    /**
     * 安装工作进程信号
     */
    public function reInstallSignal()
    {
        // uninstall stop signal handler
        pcntl_signal(SIGINT, SIG_IGN, false);
        // uninstall reload signal handler
        pcntl_signal(SIGUSR1, SIG_IGN, false);
        // uninstall  status signal handler
        pcntl_signal(SIGUSR2, SIG_IGN, false);
        //child exit
        pcntl_signal(SIGCHLD, SIG_IGN, false);
    }

    /**
     * 信号处理器
     *
     * @param int $signal
     */
    public function signalHandle($signal)
    {
        switch ($signal) {
            // Stop.
            case SIGINT:
                $this->stopAll();
                break;
            // Reload.
            case SIGUSR1:
                $this->reload();
                break;
            // Show status.
            case SIGUSR2:
                $this->statistics();
                break;
        }
    }

    /**
     * 停止所有进程
     */
    public function stopAll()
    {
        foreach (self::$pid_map as $pid) {
            posix_kill($pid, SIGTERM);
        }

        exit(0);
    }

    /**
     * 重启进程
     */
    public function reload()
    {
        if (self::$master_pid == posix_getpid()) {
            $this->forkMasterProcess();
        }
    }

    /**
     * fork master，worker 进程，
     */
    public function forkMasterProcess()
    {
        foreach (self::$pid_map as $value) {
            posix_kill($value, SIGTERM);
        }

        $pid = pcntl_fork();

        if ($pid) {
            exit(0);
        }

        self::$reloading = true;
        self::$workers = [];
        self::$pid_map = [];

        $this->worker_id = spl_object_hash($this);

        self::$workers[$this->worker_id] = $this;

        $this->runAll();
    }


    public function statistics()
    {

    }

    /**
     * 保存主进程PID
     */
    public function saveMasterPid()
    {
        self::$master_pid = posix_getpid();

        if (false === file_put_contents(self::$pid_file, self::$master_pid)) {
            $this->log("master process create failed");

            exit("\nmaster process create failed\n");
        }
    }

    /**
     * fork 所有工作进程
     */
    public function forkWorks()
    {
        foreach (self::$workers as $worker_id => $worker) {
            $worker->count = $worker->count == 0 ? 1 : $worker->count;

            while (count(self::$pid_map) < $worker->count) {
                $this->forkOneWorker($worker);
            }
        }
    }

    /**
     * fork 单个工作进程
     *
     * @param object $worker
     * @throws \Exception
     */
    public function forkOneWorker($worker)
    {
        $pid = pcntl_fork();

        if ($pid > 0) {
            self::$pid_map[] = $pid;
        } elseif ($pid === 0) {
            $this->setProcessTitle("worker process");

            if (self::$status == self::STATUS_STARTING) {
                $this->resetStd();
            }

            self::$pid_map = [];
            self::$workers = [$this->worker_id => $worker];

            $worker->run();
        } else {
            throw new \Exception('fork process failed');
        }
    }

    /**
     * 工作进程执行流程
     */
    public function run()
    {
        self::$status = self::STATUS_RUNNING;

        register_shutdown_function([$this, 'fatalError']);

        $this->reInstallSignal();

        while (true) {
            if ($this->workerRunTime > $this->maximumTimes) {
                exit(0);
            }

            call_user_func($this->onCallback, $this);

            sleep(2);

            $this->workerRunTime++;
        }
    }

    /**
     * 重置标准输入输出
     *
     * @throws \Exception
     */
    public function resetStd()
    {
        if (!self::$daemonize) {
            return;
        }

        global $STDIN, $STDOUT, $STDERR;

        $handle = fopen(self::$stdoutFile, "a");

        if ($handle) {
            unset($handle);
            @fclose(STDIN);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDIN = fopen(self::$stdoutFile, "a");
            $STDOUT = fopen(self::$stdoutFile, "a");
            $STDERR = fopen(self::$stdoutFile, "a");
        } else {
            throw new \Exception('can not open stdoutFile ' . self::$stdoutFile);
        }
    }

    /**
     * 监控子进程退出，外部命令，信号
     */
    public function monitorWorkers()
    {
        self::$status = self::STATUS_RUNNING;

        while (true) {
            pcntl_signal_dispatch();

            $pid = pcntl_wait($status, WUNTRACED);

            pcntl_signal_dispatch();

            if ($pid > 0) {
                $index = array_search($pid, self::$pid_map);

                if ($index === false) {
                    $this->stopAll();
                }

                unset(self::$pid_map[$index]);

                self::$pid_map = array_values(self::$pid_map);

                $this->forkOneWorker(self::$workers[$this->worker_id]);
            } else {
                $this->stopAll();
            }
        }
    }
}