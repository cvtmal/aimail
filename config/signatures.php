<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Email signatures per account
|--------------------------------------------------------------------------
|
| List the signature text for each logical account ID used throughout the
| application. The special key "default" will be used when no match is
| found. Signatures are stored as plain text (multiple lines); they will be
| combined with the reply and converted to HTML for the outgoing email.
*/

return [
    'info' => <<<'TXT'
Freundliche Grüsse

myitjob gmbh
Sonneggstrasse 61
8006 Zürich

www.myitjob.ch
TXT,

    'damian' => <<<'TXT'
Freundliche Grüsse

Damian Ermanni

myitjob gmbh
Sonneggstrasse 61
8006 Zürich

www.myitjob.ch
TXT,

    'default' => <<<'TXT'
Freundliche Grüsse

Lucas Baldauf
Geschäftsführer

myitjob gmbh
Sonneggstrasse 61
8006 Zürich

www.myitjob.ch
TXT,
];
