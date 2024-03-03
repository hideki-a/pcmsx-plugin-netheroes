<?php

declare(strict_types=1);

namespace NetHeroes;

use Mecab;

class SearchSupport
{
    /**
     * キーワードの調整
     */
    public static function adjustKeywords(string $keyword): string
    {
        $adoptedKeywords = [];
        $normalizedKeyword = mb_convert_kana($keyword, 'asKV');
        $keywords = explode(' ', $normalizedKeyword);

        $mecab = new Mecab\Tagger();
        $ignore_parts = [
            '助詞',
        ];

        foreach ($keywords as $item) {
            if (strpos($item, '"') !== false) {
                $adoptedKeywords[] = $item;
                continue;
            }

            $nodes = $mecab->parseToNode($item);
            foreach ($nodes as $node) {
                $stack = '';
                $featureData = $node->getFeature();
                $feature = explode(',', $featureData);

                if ($feature[0] === 'BOS/EOS') {
                    continue;
                }

                if (in_array($feature[0], $ignore_parts)) {
                    if ($stack) {
                        $adoptedKeywords[] = $stack;
                        $stack = '';
                    }
                } elseif ($feature[6] === '*') {
                    // 英数字の固有名詞
                    $stack .= $node->getSurface();
                } else {
                    $stack .= $feature[6];
                }

                if ($stack) {
                    $adoptedKeywords[] = $stack;
                }
            }
        }

        return implode(' ', $adoptedKeywords);
    }
}
