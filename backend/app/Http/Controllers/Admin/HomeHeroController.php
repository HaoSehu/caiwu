<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeHero\UpdateHomeHeroRequest;
use App\Services\Content\HomeHeroService;
use App\Services\Content\MediaFileService;

class HomeHeroController extends Controller
{
    public function __construct(
        private readonly HomeHeroService $homeHeroService,
        private readonly MediaFileService $mediaFileService,
    ) {}

    /**
     * 读取官网首页 Hero 轮播配置。
     */
    public function show()
    {
        $hero = $this->homeHeroService->getHero();

        return $this->success([
            'slides' => $hero['slides'],
            'features' => $hero['features'],
            'defaults' => [
                'slides' => $this->homeHeroService->defaultSlides(),
                'features' => $this->homeHeroService->defaultFeatures(),
            ],
            'options' => [
                'shape' => HomeHeroService::SHAPE_OPTIONS,
                'ribbon_type' => HomeHeroService::RIBBON_TYPE_OPTIONS,
                'videos' => $this->mediaFileService->listHeroVideos(),
            ],
        ]);
    }

    /**
     * 保存官网首页 Hero 轮播配置。
     */
    public function update(UpdateHomeHeroRequest $request)
    {
        $data = $request->validated();
        $saved = $this->homeHeroService->saveHero(
            (array) ($data['slides'] ?? []),
            (array) ($data['features'] ?? [])
        );

        return $this->success($saved, '首页 Banner 已保存');
    }
}
