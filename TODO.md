# TODO
# gate server
- Simple job processing for gates => create job -> if fail we mark as fail
- SyncBetService => Fire to server => IGNORE if cronjob is already running based on site/merchant_id
 
- Convert PDO process to use MYSQLi - sacrifice performance for simplicity
- Cronjob swoole server should be run on a ticker timer => pooling to database feature must work 

# cron server
- Write the cronjob server that executes the based on php files 

# infra
- Graceful shutdown/restarts `IS A MUST`
- Add redis/database to store whole fleet process count


# 15 August -monitoring the new server
- Gate http queue store mechanism
- Cronjob mechanism
- BatchProcessing?
# JOB MECHANISM
- store -> run immediately -> if fail, move status to IN_QUEUE
- cronjob to run

# YK
- Reduce the 6hours interval to maybe 2hours instead.

# 16 August - Deployment and Stress Testing 
- Stress testing the application till it breaks completely
- mysql 

# Workflow Current
=> fire by merchant that are active - get merchant by active status  
    =>get all the merchants active sites 
        OLD => save as DB for node js server to do polling one by one
        NEW => instead of fire one by one, we fire to gates to store all jobs. then fire as coroutines instead

# Key considerations
- memory usage when coroutines are being overused
- throttling workers - there's a way to do it safely => can we use redis? 

# Requirements
=> throttling workers of 2000 RPS
=> when workers are in queue, how to handle the queue 
=> throttling 200 RPS, don't go over than that. 

# DONE 
- Prevent gate jobs from running if MID/MERCHANT_ID already running
- Prevent batch job re-runs