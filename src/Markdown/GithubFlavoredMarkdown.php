<?php

namespace App\Markdown;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Extra\Markdown\MarkdownInterface;

final class GithubFlavoredMarkdown implements MarkdownInterface
{
    private GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $this->converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function convert(string $body): string
    {
        $t = $this->converter->convert($body)->getContent();
        dump($t);
        return $t;
    }
}