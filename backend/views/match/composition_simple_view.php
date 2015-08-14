<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/**
 * @var $this yii\web\View
 * @var $teamId int
 * @var $team string 
 * @var $dataProvider 
 * @var $compositionForm 
 * @var $composition 
 */
?>
<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="glyphicon glyphicon-list"></i> Состав команды
        </h3>
    </div>
    <?php
        echo GridView::widget([
            'summary' => '',
            'options' => ['class' => 'team-list'],
            'rowOptions' => function ($model, $index, $widget, $grid){
                return !$model->is_basis ? ['class' => 'danger'] : ['class' => 'success'];
            },
            'dataProvider' => $dataProvider,
            'columns' => [
                [
                    'class' => 'yii\grid\SerialColumn',
                    'options' => ['width' => '30', 'class' => 'text-center'],
                ],
                [
                    'label' => 'Игрок',
                    'value' => function($model) {
                        $num = isset($model->number) && $model->number ? ' #'.$model->number : '';
                        return isset($model->contract) ? $num." ".$model->contract->player->lastname." ".$model->contract->player->firstname : null;
                    },
                ],
            ],
        ]);   
    ?>
</div>