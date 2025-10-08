<?php

namespace IBRExplorer\Api\Enum;

enum ActionMethod: string {

    case Get = 'get';
    case Post = 'post';
    case Put = 'put';
    case Delete = 'delete';
    case Options = 'options';

}