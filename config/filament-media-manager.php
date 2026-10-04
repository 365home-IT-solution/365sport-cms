<?php

return [
    "model" => [
        "folder" => \TomatoPHP\FilamentMediaManager\Models\Folder::class,
        "media" => \TomatoPHP\FilamentMediaManager\Models\Media::class,
    ],

    "api" => [
        "active" => false, // tắt: API này cho MỌI khách đăng nhập (Sanctum) liệt kê thư mục media của quản trị
        "middlewares" => [
            "api",
            "auth:sanctum"
        ],
        "prefix" => "api/media-manager",
        "resources" => [
            "folders" => \TomatoPHP\FilamentMediaManager\Http\Resources\FoldersResource::class,
            "folder" => \TomatoPHP\FilamentMediaManager\Http\Resources\FolderResource::class,
            "media" => \TomatoPHP\FilamentMediaManager\Http\Resources\MediaResource::class
        ]
    ],

    "user" => [
      'column_name' => 'fullname',
    ],
];
