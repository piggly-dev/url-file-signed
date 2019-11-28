# Create secures Images URLs with a limited lifetime
[![Latest Version on Packagist](https://img.shields.io/packagist/v/piggly/url-file-signer.svg?style=flat-square)](https://packagist.org/packages/piggly/url-file-signer) [![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md) 

This package was made inspired by [`spatie/url-signer`](https://github.com/spatie/url-signer) and **Facebook** Images URL Schemas. It can create a file URL with a limited lifetime and a signature checker. But, it also includes some features, such as:

1. Hide, across encoding, the file path;
2. Sign URL query strings;
3. Append file parameters.

> With this library, your file path `/path/to/file/image.jpg` will be converted to something like `https://cdn.example.com/564463774e6a45334e445934556a63304e6b5a584e6a59324f545a444e6a553d/image.jpg?oe=5DE6B01A&oh=22465b117a955a23728f306e3707eea5`.

## Installation

This package can installed via **Composer**:
```bash
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
4.  `Domain`: with `od` as default. But, you have to enable it by using `enableDomainParam()` method. It checks if the domain is the same in the URL host provider.

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

#### The File Entity

To maintain **File Separator**, **Parameters Identifiers** and **Parameters Values** a `File` class was created at version `1.0.1`.  Before use the signed-object and sign a URL, you need to create a `File` class which contains all data related to your file and assign to it a `ParameterDict`. As below:
```php
use Piggly\UrlFileSigner\Collections\ParameterDict;
use Piggly\UrlFileSigner\Entities\File;

// Images allowed parameters
$imagesDict = ParameterDict::create()->add('version')->add('size')->add('compression');

// Create a file entity
$file = File::create( $imagesDict )->set('/path/to/file/image.jpg');
```

#### Customizing File Separator

The File Entity allows you to change the default file separator `_` to whatever you want. Just use:
```php
// Now, file separator will be '__'
$file->changeSeparator('__');
```

#### Identifying Parameters

To prepare the File Entity to identify file parameters will be necessary to create a `ParameterDict` instance. It’s a collection that manages parameter identifiers and aliases. First, create your `ParameterDict` containing all allowed file parameters in the file name:
```php
// [Tip] You can create a ParameterDict for each file type
$imageDict = ParameterDict::create()->add('version')->add('size')->add('compression');
$pdfDict   = ParameterDict::create()->add('version');
```
> Think about ParameterDict class as "a list of parameters allowed and how they are organized".

A **Parameter Identifier** has a unique literal name related to it and an alias to lookup in the file name. The Parameter Dictionary will auto-generates an alias based on the first letter of parameter literal name. However, you can customize alias as you want.

```php
// Files name will contain _c([a-z0-9]+)? parameter
$imageDict->add('compression');

// Files name will contain _xx([a-z0-9]+)? parameter (using xx as alias)
$imageDict->add('contrast', 'xx');
```

Soon after creating the Parameter Dictionary, associate it while create the File Entity:

```php
// Create a file entity including a ParameterDict class
$file = File::create( $imagesDict )->set('/path/to/file/image.jpg');
```

#### Sorting Parameters in Parameter Dictionary

By default, the File Entity will lookup into file name and generates URI Schema following the order you have added the file parameters in Parameter Dictionary. But, sometimes, you need to change the order of parameters to make things more interesting.

There are two methods in the File Entity to doing this. And you can call them anytime before calling any file name or URL generator. To sort how parameters will show in the URL schema. Then, call `sortToDisplay()` method. As below:
```php
// It will show first the version parameter
$file->sortToDisplay(['version']);

// It will show first the version parameter, later size parameter
$file->sortToDisplay(['version','size']);
```
And, to sort order that the File Entity needs to generates the file name call `sortInFileName()` method. As below:
```php
// In file name the version parameter cames first
$file->sortInFileName(['version']);

// In file name the size parameter cames first, then cames the version parameter
$file->sortInFileName(['size','version']);
```
Below, a real world example:

```php
// Setting all allowed parameters to image files
$imagesDict = ParameterDict::create()
    			->add('brightness')
    			->add('compression','x')
    			->add('contrast')
    			->add('version')
    			->add('size');

// The file name structure will be: file_b50_x82_c50_v1_s1080.jpg
// The generated URL paths will be: /b50/x82/c50/v1/s1080/...

// The File Entity can recognize these parameters
$file = File::create( $imagesDict );

// You changed the URL display order
$file->sortToDisplay( ['version','size','compression'] );
// You changed the file name order
$file->sortToDisplay( ['brightness','contrast','compression','size'] );

// The new file name structure will be: file_b50_c50_x82_s1080_v1.jpg
// The new generated URL Schema will be: /v1/s1080/x82/b50/c50/...
```

> **All file parameters are optional**. It means you don't need to worry about files which has or which not. All you need to care about is sorting display URLs and order in the file names properly if needed. The File Entity will do the rest.

#### Adding Parameters to a File Entity

To add Parameters Values to a File Entity, it is so simple as:
```php
// The parameters attribute is a ParameterCollection class
$file->parameters->add( 'size', 1080 )->add( 'version', 1080 );
```

> The `parameters` attribute, a `ParameterCollection` class, has the methods:
> * A public `allowed` attribute to manipulate the `ParameterDict` class associeted to the `File` ;
> * `delete($name)` to delete a parameter . Will return `\self`;
> * `replace($name, $value)` to replace a parameter value. Will return `\self`;
> * `get($name)` to get a parameter value. Will return the parameter value;
> * `onlyParams($names)` will return in an `array` only parameters `$names`;
> * `params()` will return all parameters `array`;
> * `paramsToFileName()` will return an `array` with parameters formed to the file name;
> * `paramsToDisplay()` will return an `array` with parameters formed to display in URL Schema;
> * `valueExists($name)` will check if a value exists;
> *  `names()` and `values()` will return only parameters names or parameters values;
> * `count()` will return the parameters count. 

#### File Entity methods

The File Entity has a lot of useful methods, the most commons are below:

> * `changeSeparator($separator)` will change the default file separator;
> * `getName($name)`, `getExtension($ext)` and `getPath($path)` will, respectively, get the name, the extension and the path of file;
> * `getFileName()` will form and return the full file name with path and extension;
> * `getFileNameEncoded()` will form and return the full file name with encoded path and extension;
> * `getFileNameDecoded()` will form and return the full file name with decoded path and extension. The file path needs to be encoded to use this function;
> * `set($fileName)` will set the name, the extension and the path of file;
> * `setName($name)`, `setExtension($ext)` and `setPath($path)` will, respectively, set the name, the extension and the path of file;
> * `setRandomName()` will create a unique and numeric random name to your file by using timestamp and random functions. The resulted pattern will be `[0-9]{8,}_[0-9]{15,}_[0-9]{19}`;
> * `sortToDisplay($newSort)` will sort parameters to display in the URL Scheme;
> * `sortInFileName($newSort)` will sort parameters to insert in the file name.

All others methods will be automatic used by the signed-object.

### Generating Signed URLs

Signed URLs are created and validated by the signer-object. It can be generated by providing the **File Entity** and a **Time-To-Live** in `DateInterval` format to the `sign()` method:
```php
use Piggly\UrlFileSigner\Collections\ParameterDict;
use Piggly\UrlFileSigner\Entities\File;
use Piggly\UrlFileSigner\FileSigner;

// A parameter dictionary for images files
$imageDict = ParameterDict::create()->fill(['version', 'size', 'compression' => 'x']);

// A file with parameters size => 150
$file = File::create( $imagesDict )
				->set('/path/to/file/image.jpg')
				->parameters->fill(['size'=>150]);
				
// The generated URL will be encoded, signed and valid for 6 months
$signedUrl = FileSigner::create( 'https://cdn.example.com', 'mysecretkey' )->sign( $file, new DateInterval('P6M') );
// => https://cdn.example.com/s150/566a63774e6a45334e445934556a63304e6b5a554e6a59324f545a444e6a553d/image.jpg?op=cw&oe=5ECD709F&oh=8c569aa017a8b521afb7bf2c187d9089
```

While sign a URL you may and send query strings, such as below:
```php
// The generated URL will be encoded, signed and valid for 6 months. It also contains the query string `pid`. All sent query strings will be signed.
$signedUrl = FileSigner::create( 'https://cdn.example.com', 'mysecretkey' )->sign( $file, new DateInterval('P6M'), ['pid' => '309u5fj32958ikd' ] );
```

### Validating Signed URLs

To validate a signed URL, simple call the `validate()` method. This will return `false` when the URL is not valid, or a `array` which contains the mounted file name and the `timestamp` :
```php
$data = FileSigner::create( 'https://cdn.example.com', 'mysecretkey' )->validate('https://cdn.example.com/s150/566a63774e6a45334e445934556a63304e6b5a554e6a59324f545a444e6a553d/image.jpg?op=cw%3D%3D&oe=5ECD709F&oh=8c569a-INVALID-afb7bf2c187d9089');
// => false

$imagePath = FileSigner::create( 'https://cdn.example.com', 'mysecretkey' )->validate('https://cdn.example.com/s150/566a63774e6a45334e445934556a63304e6b5a554e6a59324f545a444e6a553d/image.jpg?op=cw%3D%3D&oe=5ECD709F&oh=8c569aa017a8b521afb7bf2c187d9089');
// => [ 'file' => '/path/to/file/image_s150.jpg', 'exp' => 1590522015 ]
```

You can use `exp` to set *HTTP Header Expires* and you can use `file` to read and return the file to browser.

## Tips

To improve the way you see this library, here we share some useful tips. Let's see:

1. If you don't want the encoded file path in the URL. Don't worry, just send the file name without using a path;
2. The encoded file path will return a long string. Almost six times bigger than your original path string. You may consider sending a path alias in URL and later, after validation, lookup to a database the original file path.

An approach to better manage paths in **File Entity** may be added to this library soon in the future.

## Customizing Signers

This package provides a signer that generates a signature by using MD5 hash. You can create your signer by implementing the `interface` `Piggly\UrlFileSigner\BaseSigner`. If you let your signer extend `Piggly\UrlFileSigner\UrlSigner` you'll only need to provide the `createSignature` method.

## Future Implementations

For now, we know that the encoded file path isn't the better approach, after all, it returns a very long string resulting in a very long URL as well. In future implementations, we will bring a new way of doing this. Feel free to contributing and solve this little problem.

Remember

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

**Piggly Studio** is a agency based in Rio de Janeiro, Brasil.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.