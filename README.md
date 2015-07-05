Install
=======

```
composer require ezsystems/repository-forms
```

Instanciate in "ezpublish/EzpublishKernel.php".

```
use EzSystems\RepositoryFormsBundle\EzSystemsRepositoryFormsBundle;

class EzPublishKernel extends Kernel
{
    /**
     * Returns an array of bundles to registers.
     *
     * @return array An array of bundle instances.
     *
     * @api
     */
    public function registerBundles()
    {
        $bundles = array(
           //..
            new EzSystemsRepositoryFormsBundle()
        );

```
