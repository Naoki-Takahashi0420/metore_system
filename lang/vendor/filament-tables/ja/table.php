<?php

return [

    'column_toggle' => [
        'heading' => '列の表示/非表示',
    ],

    'columns' => [
        'text' => [
            'actions' => [
                'collapse_list' => ':count 件を折りたたむ',
                'expand_list' => 'さらに :count 件を表示',
            ],
            'more_list_items' => 'さらに :count 件',
        ],
    ],

    'fields' => [
        'bulk_select_page' => [
            'label' => 'このページのすべての項目を一括操作の対象にする',
        ],
        'bulk_select_record' => [
            'label' => ':key を一括操作の対象にする',
        ],
        'bulk_select_group' => [
            'label' => ':title グループを一括操作の対象にする',
        ],
        'search' => [
            'label' => '検索',
            'placeholder' => '検索',
            'indicator' => '検索',
        ],
    ],

    'summary' => [
        'heading' => '合計',
        'subheadings' => [
            'all' => 'すべての :label',
            'group' => ':group の合計',
            'page' => 'このページ',
        ],
    ],

    'actions' => [
        'disable_reordering' => [
            'label' => '並び替えを終了',
        ],
        'enable_reordering' => [
            'label' => '並び替え',
        ],
        'filter' => [
            'label' => 'フィルター',
        ],
        'group' => [
            'label' => 'グループ',
        ],
        'open_bulk_actions' => [
            'label' => '一括操作',
        ],
        'toggle_columns' => [
            'label' => '列の表示切替',
        ],
    ],

    'empty' => [
        'heading' => ':model が見つかりません',
        'description' => '新しい :model を作成して開始しましょう。',
    ],

    'filters' => [
        'actions' => [
            'apply' => [
                'label' => '適用',
            ],
            'remove' => [
                'label' => '削除',
            ],
            'remove_all' => [
                'label' => 'すべてのフィルターを削除',
                'tooltip' => 'すべてのフィルターを削除',
            ],
            'reset' => [
                'label' => 'リセット',
            ],
        ],
        'heading' => 'フィルター',
        'indicator' => '有効なフィルター',
        'multi_select' => [
            'placeholder' => 'すべて',
        ],
        'select' => [
            'placeholder' => 'すべて',
        ],
        'trashed' => [
            'label' => '削除済みレコード',
            'only_trashed' => '削除済みのみ',
            'with_trashed' => '削除済みを含む',
            'without_trashed' => '削除済みを除く',
        ],
    ],

    'grouping' => [
        'fields' => [
            'group' => [
                'label' => 'グループ化',
                'placeholder' => 'グループ化',
            ],
            'direction' => [
                'label' => 'グループの並び順',
                'options' => [
                    'asc' => '昇順',
                    'desc' => '降順',
                ],
            ],
        ],
    ],

    'reorder_indicator' => 'ドラッグ＆ドロップで並び替え',

    'selection_indicator' => [
        'selected_count' => ':count 件選択中',
        'actions' => [
            'select_all' => [
                'label' => 'すべて選択 :count 件',
            ],
            'deselect_all' => [
                'label' => 'すべての選択を解除',
            ],
        ],
    ],

    'sorting' => [
        'fields' => [
            'column' => [
                'label' => '並び替え',
            ],
            'direction' => [
                'label' => '並び順',
                'options' => [
                    'asc' => '昇順',
                    'desc' => '降順',
                ],
            ],
        ],
    ],
];