# phpMAE

[![Build Status](https://travis-ci.org/CloudObjects/phpMAE.svg?branch=master)](https://travis-ci.org/CloudObjects/phpMAE) [![Join the chat at https://gitter.im/CloudObjects/phpMAE](https://badges.gitter.im/CloudObjects/phpMAE.svg)](https://gitter.im/CloudObjects/phpMAE?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

phpMAE, the *PHP* *M*icro *A*PI *E*ngine, is an opinionated serverless framework for the development, execution, and deployment of small-sized stateless Web APIs, so-called "Micro APIs".

The framework is partially built on top of the [Slim micro-framework](https://www.slimframework.com/) and leverages [PHPSandbox](https://phpsandbox.org/) to provide a safe runtime environment.

The configuration and source code for Micro APIs can be stored in [CloudObjects Core](https://cloudobjects.io/) and are deployed just-in-time to a running phpMAE instance via the [CloudObjects SDK](https://github.com/CloudObjects/CloudObjects-PHP-SDK) when a request for a specific API is received.

CloudObjects currently provides a preview release of a public hosted version of phpMAE. For development or if you wish to run your own Micro API engine, you can run phpMAE as a PHAR file, using our Docker container or directly from the source.

## Installation

**Note:** The installation steps below were tested on _macOS_, which has PHP installed by default. If you are on Linux, you may have to install PHP through your distribution's package manager first. phpMAE has not yet been tested on Windows, but support is on the roadmap.

### PHAR

This is the recommended installation method for developers to create, validate and deploy their Micro APIs.

You can grab the latest PHAR (PHp ARchive) from the [phpMAE Releases on GitHub](https://github.com/CloudObjects/phpMAE/releases).

Type `php phpmae.phar` to get a list of available CLI commands. Any commands or options that interact with CloudObjects require the [CloudObjects CLI Tool](https://cloudobjects.io/clitool) to be installed as a prerequisite.

To make the `phpmae` CLI tool globally available on your system run the following commands from the directory in which you downloaded `phpmae.phar`:

    cp phpmae.phar /usr/local/bin/phpmae
    chmod +x /usr/local/bin/phpmae

### Docker

This is the recommended installation method if you want to run Micro APIs for staging and production on your own servers (local or cloud).

A prebuilt-image is available [from the Docker Hub](https://hub.docker.com/r/cloudobjects/phpmae/). You can download it via CLI:

    docker pull cloudobjects/phpmae

When running the container you need to provide the _CO_AUTH_NS_ and _CO_AUTH_SECRET_ environment variables so that the phpMAE can authenticate itself against CloudObjects. Otherwise, only Micro APIs with [co:isVisibleTo](https://cloudobjects.io/cloudobjects.io/isVisibleTo) set to [co:Public](https://cloudobjects.io/cloudobjects.io/Public) can be run:

    docker run -e CO_AUTH_NS=example.com -e CO_AUTH_SECRET=XXXXXXXX -p 8080:80 cloudobjects/phpmae

For _CO_AUTH_NS_ use a domain that you have added to CloudObjects and that you want to use as the identity for this phpMAE instance. For _CO_AUTH_SECRET_ you need to retrieve the shared secret between that domain and _cloudobjects.io_. You can retrieve this secret with the [CloudObjects CLI Tool](https://cloudobjects.io/clitool) using the following command:

    cloudobjects domain-providers:secret example.com cloudobjects.io

### Source

This installation method is only recommended if you want to "look under the hood" of phpMAE or run with particular options.

You need to have [Composer](https://getcomposer.org) installed globally on your system to download and install the dependencies.

You can download or `git clone` this repository from GitHub, then run `composer install` (or `make`) to install dependencies.

It's recommended to use composer to download and install with a single command:

    composer create-project cloudobjects/phpmae

You can customize your installation of phpMAE by copying `config.php.default` to `config.php` and then editing the file as per your requirements. Documentation of advanced features and configuration options will be published on the [phpMAE Wiki](https://github.com/CloudObjects/phpMAE/wiki).

phpMAE comes with unit tests. You can run them after installing the source to validate that any changes you made did not break the tests:

    vendor/bin/phpunit

## Getting Started Guide

### Create a class

Micro APIs compatible with phpMAE are implemented as PHP classes. They are represented as objects on CloudObjects with the type [phpmae:Class](https://cloudobjects.io/phpmae.cloudobjects.io/Class). All public methods on these classes are made accessible via RPC protocols (currently [JSON-RPC](https://www.jsonrpc.org/specification)) and other classes that add your class as a dependency. If you want your class to expose a single entry point you can add [PHP's magic __invoke()](https://secure.php.net/manual/en/language.oop5.magic.php#object.invoke) to it and make its object an instance of the type [phpmae:HTTPInvokableClass](https://cloudobjects.io/phpmae.cloudobjects.io/HTTPInvokableClass).

Like all objects, phpMAE classes are uniquely identified with COIDs (*C*loud *O*bject *ID*entifiers). COIDs are namespaced into domains, and you can create objects with COIDs only for domains that you have created or been assigned to in CloudObjects. You can see those domains in the [CloudObjects Dashboard](https://cloudobjects.io/dashboard).

To create a new class, choose a COID and then run the following command:

    phpmae class:create --confjob=true coid://NAMESPACE/NAME/VERSION

If you want to create an HTTP-invokable class, add the option `--http-invokable=true`.

This command writes two files into the current directory, namely `NAME.VERSION.xml` and `NAME.VERSION.php`. The `.xml` file contains the basic object description for CloudObjects in RDF/XML format and the `.php` file contains the skeleton code for the PHP class. It also creates a configuration job to register the COID.

Open the `.php` file and start inserting your code.

### Validate and test locally

To check whether your code is valid PHP and also respects the constraints of phpMAE regarding whitelisted functions and classes run the following command:

    phpmae class:validate coid://NAMESPACE/NAME/VERSION

You can add `--watch=true` to continuously watch for changes in the file and automatically revalidate.

To run your class, you can launch a local web server. Open a second terminal window or tab and run the following command:

    phpmae testenv:start

The web server runs in the foreground and can be stopped with _Ctrl + C_ (or _Cmd + C_). Go back to the first tab and run the following command:

    phpmae class:testenv coid://NAMESPACE/NAME/VERSION

Apart from deploying your code to the local web server this command also prints out the base URL of your Micro API which you can then open in a browser or query from a tool such as `curl`. The `--watch=true` option is supported for continuous redeployment.

### Deploy your class

Use the following command to deploy your class:

    phpmae class:deploy coid://NAMESPACE/NAME/VERSION

Internally, this command first validates the class, then calls the CloudObjects CLI to upload the `.php` source file as an attachment to CloudObjects Core and, if necessary, update the `.xml` file with a configuration job. Deployed classes are available for phpMAE instances within moments.

The output of the deploy command shows you the base URL of your Micro API on the public phpMAE instances. These instances require you to use HTTP Basic authentication to access your class. You have to use the namespace as the username, and the CloudObjects shared secret between that domain and _phpmae.cloudobjects.io_ as the password. The command for retrieving this secret is shown to you as well.

### Use your class on custom instances

You can use your class on your own private instances as well. Start an instance,e.g., using Docker as described above, and replace _phpmae.cloudobjects.io_ in the URL with our own instance's URL.

## Help&Support

Please join [our chat on Gitter](https://gitter.im/CloudObjects/phpMAE) and feel free to ask questions or provide feedback.

You can report bugs or suggest features through [our GitHub Issues](https://github.com/CloudObjects/phpMAE/issues). We also accept PRs with bug fixes; if you wish to contribute features, please create an issue first or discuss on chat. If you found a potential security issue, e.g., with the sandboxing feature, please do not use the public issue tracker but send an email to phpmae-security@cloudobjects.io.

Make sure you follow the [CloudObjects Blog](https://blog.cloudobjects.io/) and [@CloudObjectsIO](https://twitter.com/CloudObjectsIO) for the latest updates, guides, and tutorials.

Commercial support and hosted private instances are available from [CloudObjects Consulting](https://cloudobjects.io/consulting).

## License

phpMAE is licensed under the Mozilla Public License (see LICENSE file).