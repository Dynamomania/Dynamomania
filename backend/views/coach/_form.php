<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\date\DatePicker;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use kartik\select2\Select2;
use kartik\file\FileInput;

use common\models\Country;

/* @var $this yii\web\View */
/* @var $model common\models\Coach */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="coach-form">

    <?php $form = ActiveForm::begin(['options' => [
        'autocomplete' => 'off',
        'enctype' => 'multipart/form-data',
    ]]); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
   
    <?php
        $pluginOptions = [
            'showUpload' => false,
            'showRemove' => false,
            'overwriteInitial' => true,
            'browseLabel' => "Обзор...",
            'allowedFileExtensions' => ['jpg', 'gif', 'png'],
        ];

        if (!$model->isNewRecord && $photo->getFileUrl())
        {
            $pluginOptions['initialPreview'] = [
                Html::img($photo->getFileUrl()),
            ];
        }
        echo $form->field($model, 'photo')->widget(FileInput::classname(), [
            'options' => [
                'accept' => 'image/*',
                'multiple' => false,
                'class' => 'jcrop',
                'data-crop-ratio' => 2/3,
            ],
            'pluginOptions' => $pluginOptions,
        ]);
        echo $form->field($model, 'cropData')->hiddenInput(['id' => 'crop-data'])->label(false);
    ?>

    <?php
        echo $form->field($model, 'birthday')->widget(DatePicker::classname(), [
            'options' => ['placeholder' => 'Выберите дату рождения'],
            'removeButton' => false,
            'language' => 'ru-RU',
            'pluginOptions' => [
                'language' => 'ru',
                'autoclose' => true,
                'format' => 'yyyy.mm.dd'
            ]
        ]);
    ?>

    <?= $form->field($model, 'position')->textInput(['maxlength' => true]) ?>
    
    <?php
        echo $form->field($model, 'notes')->widget(\vova07\imperavi\Widget::className(), [
            'settings' => [
                'lang' => 'ru',
                'minHeight' => 200,
                'plugins' => [
                    // 'clips',
                    'fullscreen',
                    'table',
                    'video',
                    'fontcolor',
                ]
            ]
        ]);
    ?>

    <?php
        echo $form->field($model, 'player_carrer')->widget(\vova07\imperavi\Widget::className(), [
            'settings' => [
                'lang' => 'ru',
                'minHeight' => 200,
                'plugins' => [
                    // 'clips',
                    'fullscreen',
                    'table',
                    'video',
                    'fontcolor',
                ]
            ]
        ]);
    ?>

    <?php
        echo $form->field($model, 'coach_carrer')->widget(\vova07\imperavi\Widget::className(), [
            'settings' => [
                'lang' => 'ru',
                'minHeight' => 200,
                'plugins' => [
                    // 'clips',
                    'fullscreen',
                    'table',
                    'video',
                    'fontcolor',
                ]
            ]
        ]);
    ?>

    <?php 
        echo $form->field($model, 'country_id')->widget(Select2::classname(), [
            'data' => ArrayHelper::map(Country::find()->orderBy(['name' => SORT_ASC])->all(), 'id', 'name'),
            'language' => 'ru',            
            'options' => ['placeholder' => 'Выберите страну...'],
            'pluginOptions' => [
                'allowClear' => true
            ],
        ]);
    ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Добавить' : 'Изменить', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
