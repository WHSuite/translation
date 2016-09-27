#Translations
The WHSuite translations package is designed to use Symfony/Translation to retrieve language phrases. 

**Note:** This package will not work outside of the WHSuite system and framework as it requires several framework specific features.

---

##Usage
To use the translation system it first has to be loaded into the system, like so:

```php
$trans = new \Whsuite\Translation\Translation();

$trans->init('en'); // Initiate the language system
```

Once it's loaded up, you can call a language phrase like so:
```php
echo $trans->get('<your-phrase-key>');
```

---
##License
The code inside this package is released under The MIT License. As such you are free to do with it as you like, provided copyright notices remain in place. 

Please note that this license applies only to the code within this directory, and its sub-directories. The core WHSuite product is not covered under this license.