# php-form-validate
PHP Easy and intuitive validation of form fields

## Examples
```php
$fields = [
    '666b' => ['19','12',' 10 '],
    '666a' => 666,
];

$validator = new Validator\Validator( $fields ); // By default type is string and field is required


# Default value will be used only on not required fields 
$validator->validateAll([
    'test' => [ 'type' => 'int', 'required' => false, 'default' => '66', 'range' => '50-124', 'msg' => 'Invalid field test. Range is between 50 and 124', 'requires' => ['miss', '666a'] ],
    'omg'  => [ 'type' => 'float', 'range' => '1-1.23' ],
    'miss' => [ 'required' => false, 'length' => '1-3', 'default' => '123' ], 
    '/^666[ab]$/'  => [ 'type' => 'int', 'multiple' => 1, 'range' => '1-800', 'match' => [666,'/^\d{2}$/'], 'filter' => ['trim'] ] # TODO wrong
]);
```

## Notes

## Methods

### types
### options
### defaults
