# Disclaimer
This is a work in progress not ready to be used or seen. This work is sponsored by [Onebip](http://corporate.onebip.com)

# Recruiter
It's a Job Queue Manager built with PHP meant to be used in PHP projects. Features and characteristics:
* Jobs are made persistent on MongoDB
* Jobs are retriable with complex and customizable strategies
* Multiple queues are supported through tagging
* Jobs are stored by default in an history collection for after the fact inspection and analytics
* Built to be robust, scalable and fault tolerant

# History
Onebip is a payment system (think PayPal with mobile devices in place of credit cards), things like: payment notifications, subscription renewals, remainder messages, … are **really** important. You cannot skip or lose a job (notification are idempotent but payments are not). You cannot forgot to have completed a job (customer/merchant support must have data to do their job). You need to know if and when you can retry a failed job (external services have rate limits and are based on agreements/contracts). We have developed internally our job/queue solution called **Recruiter**. After a year in production and many *billions* of jobs we have decided to put what we have learned into a stand alone project and to make it open source.
