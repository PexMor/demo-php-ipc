<?php

declare(strict_types=1);



date_default_timezone_set("Europe/Prague");
// Shared memory
define('SHM_PROJECT_ID', 'A');
define('SHM_ID', ftok(__FILE__, SHM_PROJECT_ID));
define('SHM_DEFAULT_SIZE', 1000);
define('SHM_PERM', 0660);
define('SHARED_KEY_VAS', 1);
// Semaphore
define('SEM_PROJECT_ID', 'B');
define('SEM_PERM', 0660);
define('SEM_ID', ftok(__FILE__, SEM_PROJECT_ID));


$shm = shm_attach(SHM_ID, SHM_DEFAULT_SIZE, SHM_PERM);
$sem = sem_get(SEM_ID, 1, SEM_PERM);


function sig_handler($signo)
{
    global $sem;

    switch ($signo) {
        case SIGTERM:
            $r_rc = sem_release($sem);
            print("release:$r_rc\n");
            if (!$r_rc) {
                print("Error");
                exit(-1);
            }
            exit;
            break;
        case SIGHUP:
            $r_rc = sem_release($sem);
            print("release:$r_rc\n");
            if (!$r_rc) {
                print("Error");
                exit(-1);
            }
            break;
        default:
            print("Signal #$signo");
            // handle all other signals
    }
}
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP,  "sig_handler");

function loop($type)
{
    global $shm, $sem;
    $ts = 0;
    $st = 1;
    while (true) {
        // blocking semaphore
        $a_rc = sem_acquire($sem);
        print("acquire:$a_rc\n");
        if (!$a_rc) {
            print("Error");
            exit(-1);
        }
        if ($type == "producer") {
            $ts = time();
            $p_rc = shm_put_var($shm, SHARED_KEY_VAS, $ts);
            print("producer/put:$p_rc\n");
            if (!$p_rc) {
                print("Error");
                exit(-1);
            }
            print(date(DATE_RFC2822) . " -> " . date(DATE_RFC2822, $ts) . "\n");
            $st = rand(1, 5);
        } else { // consumer
            $l_ts = time();
            $ts = shm_get_var($shm, SHARED_KEY_VAS);
            print("consumer/get:$ts\n");
            if ($l_ts == $ts) {
                print(date(DATE_RFC2822, $l_ts) . " == " . date(DATE_RFC2822, $ts) . "\n");
            } else {
                print(date(DATE_RFC2822, $l_ts) . " >  " . date(DATE_RFC2822, $ts) . "\n");
            }
            $st = 1;
        }
        $r_rc = sem_release($sem);
        print("release:$r_rc\n");
        if (!$r_rc) {
            print("Error");
            exit(-1);
        }
        print("sleep($st)\n");
        sleep($st);
    };
}

if ($argv[1] == 'p') loop("producer");
if ($argv[1] == 'c') loop("consumer");
