<?php
$file = 'bot_engine.php';
$content = file_get_contents($file);

// Find the start and end of the broken style block
$startMarker = 'echo "<style>";';
$endMarker = '</style>";';

// Since I have many of these, I'll just find the one that has :: outside
$bad = '    </style>";
    ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; border: 2px solid var(--bg); }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
    </style>";';

$content = str_replace($bad, '    </style>";', $content);

file_put_contents($file, $content);
echo "Cleaned up double style tags and orphan CSS\n";
?>
