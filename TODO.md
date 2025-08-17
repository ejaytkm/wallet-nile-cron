# TODO
# Gate server
@TODO: How do we handle retries - put back and fire into maybe this coroutine again?
@TODO: How do we handle throttling - we need to throttle the requests to the gate server
@TODO: grafana + prometheus dashboard for monitoring the cronjob server
@Cronjob server -> should be completed 

# Swoole Infra
- Fix graceful shutdown/restarts `IS A MUST`
- A worker must stop accepting new requests when it is shutting down ~ don't worry about co-routines
- A worker must wait for all request/coroutines in its context before it can do a shutdown
- Database partitioning - how to handle the database partitioning

# Stress Testing
- ???? need to come up with some stress testing technique

# Cron server
- Write the cronjob server that executes the based on php files
- Boons cron script MUST RUN - preferably concurrently
- Cronjob swoole server should be run on a ticker timer

# Deployment
- Monday

# REQUIREMENTS
# BOON
- throttling 400 RPS, don't go over than that. 
- Wallet servers can only handle up to 1000 RPS - need to throttle if possible
- In one second, you can get quite a lot of requests, so we need to throttle it.

# JASON
- memory usage when coroutines are being overused

# Workflow Current
=> fire by merchant that are active - get merchant by active status  
    =>get all the merchants active sites 
        OLD => save as DB for node js server to do polling one by one
        NEW => instead of fire one by one, we fire to gates to store all jobs. then fire as coroutines instead

# Technical Debts
- Hot reload is not working and buggy
