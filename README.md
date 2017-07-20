# php-form-validate
**PEIV**  
PHP Easy and intuitive validation of form fields  


>Beta version. Used on some production environments
## Examples
```php
$fields = [
    '666b' => ['19','12',' 10 '],
    '666a' => 666,
];

$validator = new Validator\Validator( $fields ); // By default type is string and field is required


// Require field test of type int. Uses custom error message and requires fields 'req1' + 'req2' to be valid ( included in validation too ).
$validator->validateAll([
    // Not required ( then valid value 66 ), when value is provided it checks for integer in range between 50 and 124
    // To be valid this field requires fields miss and 666a to be valid too, else is invalid
    'test' => [ 'type' => 'int', 'msg' => 'Invalid field test. Not a number', 'requires' => ['miss', '666a'] ],
    'req1' => [ 'required' => 1, 'value' => 'test' ],
    'req2' => [ 'required' => 1, 'value' => 'test2' ],
]);

// Test field 'omg' of type float in range 1-1.23. If field is missing its value will be 1.2, because field is not marked as required.
$validator->validateAll([
    'omg'  => [ 'type' => 'float', 'range' => '1-1.23', 'default' => '1.2', 'required' => false ],
]);

// Require field '666a' or '666b' of type int in range 1-800 that value matches 666 or any 2 digit number. Trim data before check
$validator->validateAll([
    '/^666[ab]$/'  => [ 'type' => 'int', 'multiple' => 1, 'range' => '1-800', 'match' => [666,'/^\d{2}$/'], 'filter' => ['trim'] ]
]);

// Require field 'disabled' on if condition is met - $test must be a true
$validator->validateAll([
    'disabled'  => [ 'type' => 'int', 'disabled' => ( $test === true ? true : false ) ]
]);


print "Valid fields: \n";
print_r( $validator->valid );


print "Invalid fields: \n";
print_r( $validator->invalid );


print "Errors: \n";
print_r( $validator->errors );

if( $validator->hasErrors() ){
    print "Errors found: \n";

    foreach( $validator->errors as $error ){
        if( $error->field() === 'ignore' ){
            $error->handle(); // "Handle" exception, so it wont throw
            continue;
        }

        print "Field: " . $error->field() . "\n";
        print "Message: " . $error->getMessage() . "\n";

        if( $error->field() === 'critical' ){
            $error->raise(); // Throw exception on critical field
        }
    }
}
```

## Notes


## Methods

- **clear()** : Clear all valid/invalid/error fields. Used to prepare another validation. It's called on every validateAll() call
- **hasErrors()** : Test if any errors occurred
- **vaidateAll( array $tests )** : Validate given parameters
- **validate( str $field, array $params )** : Validate single field
- **setInvalid( str $field, str $error_msg, $value )** : Invalidate field and set custom error message & value. Get it with $validator->invalid[$field]
### Filters
- non_digit
- trim
- html
- lc

### Types
- int
- float
- numeric
- string DEFAULT
- char
- charnum
- email
- json
- date
- datetime
- time
- unix_time
- int_list

### Options

- type = string : Required type.
- required = true : Require field. If not required it wont raise error if is missing. If present it will use validation and raise error if invalid.
- range : Range option for int/float type field. Example forms are: '1-10', '1-1.5', '-10', '1-'
- length : Test if value is with length X. Uses same style as 'range' option.
- multiple: Field can be array of values ( when 1 field has multiple values )
- match : Match exact string and/or regex. Can be array of OR matches. Examples: match => 'test' OR match => [ 'test', '^test?$' ]
- disabled = false: When true it will exclude field from tests.
- default : Fallback value when field is not required and missing.
- msg = 'Invalid field X' : Pass here custom error message. Get with $validator->error[$field].
- value( $value ) : Override field value with custom one
- requires( [$a,$b] ) : Field requires other fields to be valid to pass validation.
- regex : Use regex to validate against
- filter : Trigger filters before validation. Example: filter => trim OR filter => [ trim, non_digit ]
### Defaults
