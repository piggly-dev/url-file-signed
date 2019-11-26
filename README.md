# Create secures Images URLs with a limited lifetime
[![Latest Version on Packagist](https://img.shields.io/packagist/v/piggly/url-file-signer.svg?style=flat-square)](https://packagist.org/packages/piggly/url-file-signer) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md) [![Build Status](https://img.shields.io/travis/piggly/url-file-signer/master.svg?style=flat-square)](https://travis-ci.org/piggly/url-file-signer) [![Quality Score](https://img.shields.io/scrutinizer/g/piggly/url-file-signer.svg?style=flat-square)](https://scrutinizer-ci.com/g/piggly/url-file-signer) [![Total Downloads](https://img.shields.io/packagist/dt/piggly/url-file-signer.svg?style=flat-square)](https://packagist.org/packages/piggly/url-file-signer)

This package was made inspired by [`spatie/url-signer`](https://github.com/spatie/url-signer) and **Facebook** Images URL Schemas. It can create a file URL with a limited lifetime and a signature checker. But, it also includes some features, such as:

1. Hide, across encoding, the file path;
2. Sign URL query strings;
3. Append file parameters.

> With this library, your file path `/path/to/file/image.jpg` will be converted to something like `https://cdn.example.com/564463774e6a45334e445934556a63304e6b5a584e6a59324f545a444e6a553d/image.jpg?oe=5DE6B01A&oh=22465b117a955a23728f306e3707eea5`.

## Installation

This package can installed via **Composer**:
```php
composer require piggly/url-file-signer
```

## URL Structure

By default, all signed files URLs will contain the following structure:

* `baseUrl` base host/domain/url;
* `/[fileParameter]/...` *(optional)* one or more files parameters;
* `/[encodedPath]` file path encoded;
* `/[file.ext]?` file name;
* `op=[orderOfParameters]` *(optional)* contains the order of parameters in the file name.
* `od=[domain]` *(optional)* domain owner.
* `oe=[expiration]` encoded expiration date.
* `oh=[signature]` signature hash;

## Usage

A signer-object can sign and validate files URL. A unique and secret key is used to generate signatures. As simple as:
```php
use Piggly\UrlFileSigner\FileSigner;

// Starting with a base url and the secret key
$fileSigner = FileSigner::create( 'https://cdn.example.com', 'mysecretkey' );
```

### Customizing Query Strings Parameters

The signed-object allows customization for the following query string parameters:

1.  `Order of Parameters`: with `op` as default. When a file has parameters, will contain an encoded list with parameters in the order they appear in the file name;
2.  `Expiration`: with `oe` as default. Will contain an encoded `timestamp` with expiration date;
3.  `Signature`: with `oh` as default. Will contain a `hash` string signature to URL;
4.  `Domain`: with `od` as default. But, you have to enable it by using `enableDomainParam()` method. It checks if the domain is the same in the URL host.

To customize each of them, use as follow:
```php
// Change "Order of Parameters" parameter name
$fileSigner->changeOrderOfParametersParam('par');
// Change Expiration parameter name
$fileSigner->changeExpirationParam('exp');
// Change Signature parameter name
$fileSigner->changeSignatureParam('sig');
// Enable and set Domain parameter name
$fileSigner->enableDomainParam('dom');
```

### Setting up Parameters for Files

In most modern systems, when saving a file, an algorithm may generate many different versions for a file. Below, is what we understand about file parameters:

> A piece of unique information that modifies the file. Eg.: sizes, compressions, versions, and so on.

To detect parameters, the signed-object will lookup into the file name and extract this parameter to pos-formatting. 

> Let's suppose some scenario where it has different `sizes` for the same `image.jpg`. Then, the image needs to has your property set in its name, such as `images_s250.jpg`, `images_s840.jpg`, and so on.

The `_` is what we called as **File Separator**. It separates one or more parameters `_s250_vprivate_c80`. And, the `s`, `v` or `c` is what we called as **Parameters Identifier**. They are an alias to identifying the next characters (`/([a-z0-9]+)?/i`) as the **Parameter Value**. All parameters are optionals values in the file name. If don’t want to catch parameters jump this section.

#### Customizing File Separator

The signed-object allows you to change the default file separator `_` to whatever you want. Just use:
```php
// Now, file separator will be '__'
$fileSigner->changeFileSeparator('__');
```

#### Identifying Parameters

To prepare the signed-object to identify file parameters will be necessary to create a `ParameterDict` instance. It’s a collection that manages parameter identifiers and aliases. First, create your `ParameterDict` containing all allowed file parameters in the file name:
```php
$fileParams = ParameterDict::create()->add('version')->add('size')->add('compression');
```

A **Parameter Identifier** has a unique literal name related to it and an alias to lookup in the file name. The Parameter Collection will auto-generates an alias based on the first letter of parameter literal name. However, you can customize alias as you want.

```php
// Files name will contain _c([a-z0-9]+)? parameter
$fileParams->add('compression');

// Files name will contain _xx([a-z0-9]+)? parameter (using xx as alias)
$fileParams->add('contrast', 'xx');
```

Soon after creating the Parameter Collection, associate it with the signed-object:

```php
// Now, the signed-object will recognize version, size, compression in file names
$fileSigner->addAllowedFileParams( $fileParams );
```

#### Sorting Parameters in Collection

By default, the signed-object will lookup into file names and generates URLs following the order you have added the file parameters in Parameter Collection. But, sometimes, you need to change parameter orders to make things more interesting.

There are two methods in Parameter Collection to doing this. And you can call them anytime before calling the `sign()` or `validate()`  from the signed-object.

To sort how parameters will show in the URL schema. Then, call `sortToDisplay()` method. As below:
```php
// It will show first the version parameter
$fileSigner->getAllowedFileParams()->sortToDisplay(['version']);

// It will show first the version parameter, later size parameter
$fileSigner->getAllowedFileParams()->sortToDisplay(['version','size']);
```
And, to sort order that the signed-object needs to generates file names call `sortInFileName()` method. As below:
```php
// In file name the version parameter cames first
$fileSigner->getAllowedFileParams()->sortInFileName(['version']);

// In file name the size parameter cames first, then cames the version parameter
$fileSigner->getAllowedFileParams()->sortInFileName(['size','version']);
```

If you want to, you can use shortcuts to call `sortToDisplay()` and `sortInFileName()` methods:

```php
$fileSigner->sortToDisplay( ... );
$fileSigner->sortInFileName( ... );
```

Below, a real world example:

```php
// Setting all allowed parameters to all kind of files
$fileParams = ParameterDict::create()
    			->add('brightness')
    			->add('compression','x')
    			->add('contrast')
    			->add('version')
    			->add('size');

// The file name structure will be: file_b50_x82_c50_v1_s1080.jpg
// The generated URL paths will be: /b50/x82/c50/v1/s1080/...

// The signed-object can recognize these parameters
$fileSigner->addAllowedFileParams( $fileParams );

// You changed the URL display order
$fileSigner->sortToDisplay( ['version','size','compression'] );
// You changed the file name order
$fileSigner->sortToDisplay( ['brightness','contrast','compression','size'] );

// The new file name structure will be: file_b50_c50_x82_s1080_v1.jpg
// The new generated URL paths will be: /v1/s1080/x82/b50/c50/...
```

> **All file parameters are optional**. It means you don't need to worry about files which has or which not. All you need to care about is sorting display URLs and order in the file names properly if needed. The signed-object will do the rest.

#### Append Parameters to a File Name

> **It's strongly recommended to use this method to attach parameters to a file name.**

To automatizing appending parameters to an image name, the signed-object provides a static method to auto-generates a new file name containing all parameters you need. As simple as:
```php
$fileParams = ParameterDict::create()->add('version')->add('size')->add('compression');
$fileSigner->addAllowedFileParams( $fileParams );

// Will return `/path/to/file/my-image_s250.jpg`.
$newName = $fileSigner::appendParamsToFileName($fileSigner,'/path/to/file/my-image.jpg', ['size'=>250]);
```

The parameters are attached to files names following the order them were added (or sorted) in Parameters Collection:
```php
$fileParams = ParameterDict::create()->add('version')->add('size')->add('compression');
$fileSigner->addAllowedFileParams( $fileParams );

// Will return `/path/to/file/my-image_s250.jpg`.
$newName = $fileSigner::appendParamsToFileName($fileSigner,'/path/to/file/my-image.jpg', ['size' => 250]);

// Will return `/path/to/file/my-image_v12_s250.jpg`.
$newName = $fileSigner::appendParamsToFileName($fileSigner,'/path/to/file/my-image.jpg', ['size' => 250,'version'=>12]);

// NOW you changed the file name order
$fileSigner->sortInFileName( ['compression','size'] );

// Will return `/path/to/file/my-image_s250.jpg`.
$newName = $fileSigner::appendParamsToFileName($fileSigner,'/path/to/file/my-image.jpg', ['size' => 250]);

// Will return `/path/to/file/my-image_s250_v12.jpg`.
$newName = $fileSigner::appendParamsToFileName($fileSigner,'/path/to/file/my-image.jpg', ['size' => 250,'version'=>12]);
```

If you want to create your own method to generate a file name. You will need to take care about:

* Make sure your image standard name doesn't contain the reserved **File Separator** character, such as: `my_image_name.jpg`. Otherwise, may be impossible to detect file parameters.

### Generating Signed URLs

Signed URLs can be generated by providing the **File Path** and a **Time-To-Live** in `DateInterval` format to the `sign()` method:
```php
// The generated URL will be encoded, signed and valid for 6 months
$signedUrl = $imageSigner->sign('/path/to/file/image_s150.jpg', new DateInterval('P6M'));
// => https://cdn.example.com/s150/566a63774e6a45334e445934556a63304e6b5a554e6a59324f545a444e6a553d/image.jpg?op=cw%3D%3D&oe=5ECD709F&oh=8c569aa017a8b521afb7bf2c187d9089
```

### Validating Signed URLs

To validate a signed URL, simple call the `validate()` method. This will return `false` when the URL is not valid, or a `string` which contains the mounted path for getting the file:
```php
$imagePath = $imageSigner->validate('https://cdn.example.com/s150/566a63774e6a45334e445934556a63304e6b5a554e6a59324f545a444e6a553d/image.jpg?op=cw%3D%3D&oe=5ECD709F&oh=8c569a-INVALID-afb7bf2c187d9089');
// => false

$imagePath = $imageSigner->validate('https://cdn.example.com/s150/566a63774e6a45334e445934556a63304e6b5a554e6a59324f545a444e6a553d/image.jpg?op=cw%3D%3D&oe=5ECD709F&oh=8c569aa017a8b521afb7bf2c187d9089');
// => /path/to/file/image_s150.jpg
```

## Tips

To improve the way you see this library, here we share some useful tips. Let's see:

1. If you don't want the encoded file path in the URL. Don't worry, just send the file name without using a path;
2. The signed-object path will return a long string. Almost six times bigger than your original path string. You may consider sending a path alias in URL and later, after validation, lookup to a database the original file path.

## Customizing Signers

This package provides a signer that generates a signature and append parameters to files names. You can create your signer by implementing the `interface` `Piggly\ImageUrlSignature\BaseSigner`. If you let your signer extend `Piggly\ImageUrlSignature\UrlSigner` you'll only need to provide the `createSignature` method.

## Future Implementations

For now, we know that the encoded file path isn't the better approach, after all, it returns a very long string resulting in a very long URL as well. In future implementations, we will bring a new way of doing this. Feel free to contributing and solve this little problem.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

This library uses [PHPUnit]( https://phpunit.de/ ).

``` bash
vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email dev@piggly.com.br instead of using the issue tracker.

## Credits

- [Caique Araujo](https://github.com/caiquearaujo)
- [All Contributors](../../contributors)

## Support us

**Piggly Studio** is a agency based in Rio de Janeiro, Brasil. You'll find an overview of all our open source projects [on our website](https://dev.piggly.com.br).

Does your business depend on our contributions? Reach out and support us on [Patreon](https://www.patreon.com/spatie). 
All pledges will be dedicated to allocating workforce on maintenance and new awesome stuff.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.