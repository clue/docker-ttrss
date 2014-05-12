# docker-ttrss

This docker image allows you to run the [Tiny Tiny RSS](http://tt-rss.org) feed reader.
Keep your feed history to yourself and access your RSS and atom feeds from everywhere.
You can access it through an easy to use webinterface on your desktop, your mobile browser
or using one of available apps.

## Quickstart

This section assumes you want to get started quickly, the following sections explain the
steps in more detail. So let's start.

Just start up a new database container:

```bash
$ DB=$(docker run -d nornagon/postgres)
```

And because this docker image is available as a [trusted build on the docker index](https://index.docker.io/u/clue/ttrss/),
using it is as simple as launching this Tiny Tiny RSS installation linked to your fresh database:

```bash
$ docker run -d --link $DB:db -p 80:80 clue/ttrss
```

Running this command for the first time will download the image automatically.

## Accessing your webinterface

The above example exposes the Tiny Tiny RSS webinterface on port 80, so that you can browse to:

http://localhost/

The default login credentials are:

Username: admin
Password: password

Obviously, you're recommended to change those ASAP.

## Installation Walkthrough

### Running

Following docker's best practices, this container does not contain its own database,
but instead expects you to supply a running instance. 
While slightly more complicated at first, this gives your more freedom as to which
database instance and configuration you're relying on.
Also, this makes this container quite disposable, as it doesn't store any sensitive
information at all.

#### Starting a database instance

This container requires a PostgreSQL database instance. You're free to pick (or build)
any, as long as is exposes its database port (5432) to the outside.

Example:

```bash
$ sudo docker run -d --name=tinystore nornagon/postgres
```

#### Testing ttrss in foreground

For testing purposes it's recommended to initially start this container in foreground.
This is particular useful for your initial database setup, as errors get reported to
the console and further execution will halt.

```bash
$ sudo docker run -it --link tinystore:db -p 80:80 clue/ttrss
```

##### Database configuration

Whenever your run ttrss, it will check your database setup. It assumes the following
default configuration, which can be changed by passing the following additional arguments:

```
-e DB_NAME=ttrss
-e DB_USER=ttrss
-e DB_PASS=ttrss
```

##### Database superuser

When you run ttrss, it will check your database setup. If it can not connect using the above
configuration, it will automatically try to create a new database and user.

For this to work, it will need a superuser account that is permitted to create a new database
and user. It assumes the following default configuration, which can be changed by passing the
following additional arguments:

```
-e DB_ENV_USER=docker
-e DB_ENV_PASS=docker
```

#### Running ttrss daemonized

Once you've confirmed everything works in the foreground, you can start your container
in the background by replacing the `-it` argument with `-d` (daemonize).
Remaining arguments can be passed just like before, the following is the recommended
minimum:

```bash
$ sudo docker run -d --link tinystore:db -p 80:80 clue/ttrss
```
