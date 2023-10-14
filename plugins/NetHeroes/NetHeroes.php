<?php
require_once LIB_DIR . 'Prototype' . DS . 'class.PTPlugin.php';
require_once LIB_DIR . 'php-markdown' . DS . 'Michelf' . DS . 'Markdown.inc.php';

use Michelf\Markdown;

class NetHeroes extends PTPlugin {

    public function __construct () {
        parent::__construct();
    }

    /**
     * PADOのpre_loadコールバックでの処理（モデル：entry）
     *
     * @access public
     * @param array $cb
     * @param PADO $pado
     * @param PADOMySQL $obj
     * @return bool
     */
    public function db_pre_load_entry( &$cb, $pado, &$obj ) {
        // 全文検索ページの時、クエリにmroonga_snippet_html関数を差し込む
        if (
            strpos( $cb[ 'sql' ], 'SELECT COUNT' ) === false &&
            strpos( $cb[ 'sql' ], 'IN BOOLEAN MODE' ) !== false
        ) {
            $keyword = $cb[ 'values' ][ 2 ]; // NOTE: クエリが変わらない限り位置は変わらないだろうからとりあえず
            $replace = ", mroonga_snippet_html( `entry_search_text`, '{$keyword}' AS query ) AS mroonga_snippet FROM";
            $cb[ 'sql' ] = str_replace( 'FROM', $replace, $cb[ 'sql' ] );
        }

        return true;
    }

    /**
     * post_initフックでの処理
     *
     * @access public
     * @param Prototype $app
     * @return void
     */
    public function post_init( &$app ) {
        if ( preg_match( '/^\/search\/mroonga.html/u', $app->request_uri ) ) {
            $app->db->register_callback( 'entry', 'pre_load', 'db_pre_load_entry', 5, $this );
        }
    }

    /**
     * 記事一覧取得前の処理
     *
     * @access public
     * @param array $cb
     * @param Prototype $app アプリケーション
     * @param array $terms 検索条件
     * @param array $args 抽出条件
     * @param string $extra 追加のクエリ
     * @param array $extra_values 追加のクエリにバインドする値
     * @return void
     */
    public function pre_listing_entry ( &$cb, $app, &$terms, &$args, &$extra, &$extra_values ) {
        if ( $app->id !== 'Bootstrapper' ) {
            return;
        }

        // Mroonga検索ページの処理
        if ( preg_match( '/^\/search\/mroonga.html/u', $app->request_uri ) ) {
            if ( $app->param( 'keyword' ) ) {
                $query = '*D+ ' . preg_replace( '/　/u', ' ', $app->param( 'keyword' ) );
                $extra .= <<<EOQ
    AND MATCH( `entry_title`,`entry_search_text`) AGAINST (? IN BOOLEAN MODE)
    ORDER BY MATCH( `entry_title`,`entry_search_text`) AGAINST (? IN BOOLEAN MODE) DESC
EOQ;
                $extra_values[] = $query;
                $extra_values[] = $query;
            } else {
                $terms[ 'id' ] = -1;
            }
        }
    }

    /**
     * 記事オブジェクト保存前の処理
     *
     * @access public
     * @param array $cb
     * @param Prototype $app アプリケーション
     * @param PADOMySQL $obj 記事オブジェクト
     * @return bool
     */
    public function pre_save_entry ( &$cb, $app, &$obj ) {
        // FAQワークスペースで保存時に検索用テキストを作成する
        // TODO: Mroongaの仕様をもう少し確認
        if ( (int) $obj->workspace_id === 5 ) {
            $app->get_scheme_from_db( 'entry' );
            $block_data = json_decode( $obj->block_edit, false );
            $text = '';

            foreach ( $block_data as $block ) {
                if ( $block->type === 'FAQMarkdown' ) {
                    $html = Markdown::defaultTransform( $block->text );
                    $text .= html_entity_decode( strip_tags( $html ) );
                } elseif ( $block->type === 'FAQText' ) {
                    $text .= html_entity_decode( strip_tags( $block->text ) );
                } elseif ( $block->type === 'FAQCode' ) {
                    $text .= $block->code;
                }
            }

            $obj->search_text( $text );
        }

        return true;
    }

}
