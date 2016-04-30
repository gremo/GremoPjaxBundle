# PjaxBundle
[![Latest stable](https://img.shields.io/packagist/v/gremo/pjax-bundle.svg?style=flat-square)](https://packagist.org/packages/gremo/pjax-bundle) [![Downloads total](https://img.shields.io/packagist/dt/gremo/pjax-bundle.svg?style=flat-square)](https://packagist.org/packages/gremo/pjax-bundle) [![GitHub issues](https://img.shields.io/github/issues/gremo/GremoPjaxBundle.svg?style=flat-square)](https://github.com/gremo/GremoPjaxBundle/issues)

Symfony bundle that provide a lightweight yet powerfull integration with [pjax](https://github.com/defunkt/jquery-pjax) jQuery plugin.

New contributors are welcome!

## Installation
Add the bundle in your `composer.json` file:

```js
{
    "require": {
        "gremo/pjax-bundle": "~1.0"
    }
}
```
Then enable the bundle in the kernel:

```php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Gremo\PjaxBundle\GremoPjaxBundle(),
        // ...
    );
}
```

## Configuration
Integration is **disabled by default**, see "Usage" to find out which method you need to enable.

Short configuration:
```yml
# GremoPjaxBundle Configuration
    annotations:          false # should annotations be enabled?
	controller_injection: false # should controller injection be enabled?
```

Full configuration and defaults:

```yml
# GremoPjaxBundle Configuration
gremo_pjax:
	# Annotations configuration
    annotations:
		enabled: false # should annotations be enabled?
		# Annotation defaults (see "Annotations")
		defaults:
			version: ~
			filter:  true

	# Controller injection configuration
    controller_injection:
        enabled: false # should controller injection be enabled?
		# How controller parameters should be named?
        parameters:
			X-PJAX: _isPjax
			X-PJAX-Container: _pjaxContainer
```

## Usage
This bundle provides two different types of integration: annotations and controller injection.

## Annotations
This is the most unobtrusive way and it's fully automatic:

- You don't need custom template logic or controller logic
- Response HTML is automatically filtered (il `filter` option is `true`) and a `<title>` tag is injected in the pjax container fragment
- Response time will slightly increase due to the filtering logic, but you still save bandwidth

> **Note**: everything that is not a successfull response or text/html is simply ignored.

Available options:
- `version` (`string`, default `null`): sets the pjax version (see [Layout Reloading](https://github.com/defunkt/jquery-pjax#layout-reloading))
- `filter` (`bool`, default `true`): whatever respose should contain only the pjax container or the full HTML 

> **Note**: annotations defined on a controller action **inherit from class annotation** and replace defaults from configuration.

The `@Pjax` annotation on a controller class defines all action routes as pjax-aware:

```php
<?php

use Gremo\PjaxBundle\Annotation\Pjax;

/**
 * @Pjax(version="1.2")
 */
class DefaultController extends Controller
{
    // ...
}
```

Instead, on a controller action, the annotation defines the route as pjax-aware:

```php
<?php

use Gremo\PjaxBundle\Annotation\Pjax;

class DefaultController extends Controller
{
    /**
     * @Pjax(filter=false)
     */
    public function indexAction(Request $request)
    {
        // ...
    }
}
```

## Controller injection
This is the most obtrusive way but potentially the most powerful one:

- You need to define the pjax action parameters (names are configurable)
- You need to define a custom template/controller logic for returing the full HTML or just the pjax container
- Extra logic allows to save queries and reduce the response time 

```php
<?php

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request, $_isPjax, $_pjaxContainer)
    {
        if ($_isPjax) {
            // Return just the pjax container HTML if pjax is enabled
            // ...
        }

        // Return the full layout
        // ...
    }
}
```
