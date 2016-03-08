<?php
require_once __DIR__ . '/vendor/autoload.php';

$config = array(
    'compilePath' => __DIR__ . '/var/ckcr_compiled/',
    'preDefined' => array(
        'user' => 'aarr|include:name;id{
            name: str,
            id: int,
        }',
        'show' => 'aarr|include:id;url{
            id: int,
            url: str,
        }',
    ),
);

$data = array(
    'user' => array(
        'id'   => 1,
        'name' => 'goosman-lei',
        'age'  => 30,
    ),
    'shows' => array(
        array(
            'id' => 1,
            'url' => 'http://img.oneniceapp.com/1.jpg',
        ),
        array(
            'id' => 2,
            'url' => 'http://img.oneniceapp.com/2.jpg',
        ),
    ),
);


$ckcrHandler = new \Ckcr\Handler($config);



echo '==================直接的应用' . chr(10);
$ckcr = 'aarr{
    user: aarr|include:name;id{
        name: str,
        id: int,
    },
    shows: iarr{
        *: aarr|include:url{
            url: str,
        }
    }
}';
$ckcrProxy = $ckcrHandler->getProxy($ckcr);
$ckcrProxy->ckcr($data);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . chr(10);


echo '==================带预处理的例子' . chr(10);
$ckcr = 'aarr{
    user: {@user@},
    shows: iarr{
        *: {@show@},
    }
}';
$ckcrProxy = $ckcrHandler->getProxy($ckcr);
$ckcrProxy->ckcr($data);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . chr(10);
