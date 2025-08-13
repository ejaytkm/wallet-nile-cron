# TODO
- Simple job processing for gates => create job -> if fail we mark as fail
- Fire to server => IGNORE if cronjob is already running based on site/merchant_id
- cron job to run http_job_queue then run syncbethistory
- Prevent job -> cronjob
- Prevent batch job re-runs
- Graceful shutdown/restarts
- Monitoring 

# JOB MECHANISM
- store -> run immediately -> if fail, move status to IN_QUEUE
- cronjob to run

# YK
- Reduce the 6hours interval to maybe 2hours instead. 

# Workflow Current
=> fire by merchant that are active - get merchant by active status  
    =>get all the merchants active sites 
        => 
# Workflow Proposal 
=> fire by merchant that are active - get merchant by active status  
    => check database to order merchant priority
        => get all the merchants => active betting providers 
            =>


# Key considerations
- memory usage when coroutines are being overused
- throttling workers - there's a way to do it safely => can we use redis? 

# Required
~ max 100,000 workers
dispatcher -> workers = will have low 
