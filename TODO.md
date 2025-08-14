# TODO
- Simple job processing for gates => create job -> if fail we mark as fail
- Fire to server => IGNORE if cronjob is already running based on site/merchant_id
- cron job to run http_job_queue then run syncbethistory
- Prevent job -> cronjob
- Prevent batch job re-runs
- Monitoring
- Graceful shutdown/restarts `IS A MUST`

14 August
# Gate http queue store mechanism
# Cronjob mechanism
# BatchProcessing?

15 August monitoring the new server


# JOB MECHANISM
- store -> run immediately -> if fail, move status to IN_QUEUE
- cronjob to run

# YK
- Reduce the 6hours interval to maybe 2hours instead. 

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