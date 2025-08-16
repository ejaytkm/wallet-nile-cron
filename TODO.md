# TODO
# Gate server
// @TODO: How do we handle retries - put back and fire into maybe this coroutine again?
// @TODO: Update the cronjob - MOVE back to PENDING for reprocessing
// @TODO: IGNORE if cronjob is already running based on site/merchant_id

# Cron server
- Cronjob swoole server should be run on a ticker timer => pooling to database feature must work
- Write the cronjob server that executes the based on php files 

# infra
- Graceful shutdown/restarts `IS A MUST`
- Add redis/database to store whole fleet process count
- Hot reload not exactly reloading

# monitoring
- grafana dashboard for monitoring the cronjob server

# 16 August -monitoring the new server
- Gate http queue store mechanism
- Cronjob mechanism
# JOB MECHANISM
- store -> run immediately -> if fail, move status to IN_QUEUE
- cronjob to run
- Stress testing the application till it breaks completely
- mysql

# REQUIREMENTS
# BOON
- throttling workers of 2000 RPS
- when workers are in queue, how to handle the queue
- throttling 200 RPS, don't go over than that.
- Wallet servers can only handle up to 1000 RPS - need to throttle if possible
- throttling workers - there's a way to do it safely => can we use redis?

# YK
- Reduce the 6hours interval to maybe 2hours instead.

# JASON
- memory usage when coroutines are being overused

# Workflow Current
=> fire by merchant that are active - get merchant by active status  
    =>get all the merchants active sites 
        OLD => save as DB for node js server to do polling one by one
        NEW => instead of fire one by one, we fire to gates to store all jobs. then fire as coroutines instead