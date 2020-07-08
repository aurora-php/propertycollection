# PropertyCollection

Collection data structure with dot-notation support.

## Usage

Example:

```php
<?php

require_once 'vendor/autoload.php';

$p = new \Octris\PropertyCollection([
    'first' => '1',
    'second' => [
        'first' => '2.1'
    ],
    'third' => [
        'first' => [
            'first' => '3.1.1'
        ]
    ]
]);

print $p->get('first') . "\n";
print $p->get('second.first') . "\n";

$x = $p->get('third.first');
$x->set('second.0', 'a3.1');
$x->set('second.1', 'a3.2');

foreach ($p->get('third.first.second') as $k => $v) {
    printf("%s => %s\n", $k, $v);
}
```

Output: 

    1
    2.1
    0 => a3.1
    1 => a3.2
