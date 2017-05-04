# php-form-validate
PHP Easy and intuitive validation of form fields
Early version, use at own risk!
## Examples
```php
$fields = [
    '666b' => ['19','12',' 10 '],
    '666a' => 666,
];

$validator = new Validator\Validator( $fields ); // By default type is string and field is required


# Default value will be used only on not required fields 
$validator->validateAll([
    // Not required ( then valid value 66 ), when value is provided it checks for integer in range between 50 and 124
    // To be valid this field requires fields miss and 666a to be valid too, else is invalid
    'test' => [ 'type' => 'int', 'required' => false, 'default' => '66', 
        'range' => '50-124', 'msg' => 'Invalid field test. Range is between 50 and 124',
        'requires' => ['miss', '666a'] ],
    'omg'  => [ 'type' => 'float', 'range' => '1-1.23' ],
    'miss' => [ 'required' => false, 'length' => '1-3', 'default' => '123' ], // Not required, which defaults to value 123 when missing
    '/^666[ab]$/'  => [ 'type' => 'int', 'multiple' => 1, 'range' => '1-800', 'match' => [666,'/^\d{2}$/'], 'filter' => ['trim'] ] // Regex match field, with optional list value
]);
```

## Notes

## Methods

### types
### options
### defaults
