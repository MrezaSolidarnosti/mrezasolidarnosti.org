<?php

namespace Solidarity\Post\Factory;


use Skeletor\Core\Factory\AbstractFactory;
use Skeletor\Image\Entity\Image;
use Solidarity\Post\Entity\Post;

class PostFactory extends AbstractFactory
{
    public static function compileEntityForCreate($data, $em): ?int
    {
        $post = new Post();
        $post->title = $data['title'];
        $post->slug = $data['slug'];
        $post->shortDescription = $data['shortDescription'];
        $post->status = $data['status'];
        $post->publishAt = $data['publishAt'];
        $post->blockData = $data['blockData'];
        if(isset($data['featuredImageId'])) {
            $image = $em->getRepository(Image::class)->find($data['featuredImageId']);
            $post->featuredImage = $image;
        }
        $post->seoTitle = $data['seoTitle'] ?? $post->title;
        $post->seoDescription = $data['seoDescription'] ?? $post->shortDescription;
        if(isset($data['seoImageId'])) {
            $image = $em->getRepository(Image::class)->find($data['seoImageId']);
            $post->seoImage = $image;
        }
        $em->persist($post);
        $em->flush();

        return $post->id;
    }

    public static function compileEntityForUpdate($data, $em)
    {
        $post = $em->getRepository(Post::class)->find($data['id']);
        $post->title = $data['title'];
        $post->slug = $data['slug'];
        $post->status = $data['status'];
        $post->publishAt = $data['publishAt'];
        $post->blockData = $data['blockData'];
        $post->shortDescription = $data['shortDescription'];
        if(isset($data['featuredImageId'])) {
            $image = $em->getRepository(Image::class)->find($data['featuredImageId']);
            $post->featuredImage = $image;
        } else {
            $post->featuredImage = null;
        }
        $post->seoTitle = $data['seoTitle'] ?? $post->title;
        $post->seoDescription = $data['seoDescription'] ?? $post->shortDescription;
        if(isset($data['seoImageId'])) {
            $image = $em->getRepository(Image::class)->find($data['seoImageId']);
            $post->seoImage = $image;
        } else {
            $post->seoImage = null;
        }

        return $post->id;
    }
}