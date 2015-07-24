<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use dosamigos\selectize\SelectizeDropDownList;

/* @var $this yii\web\View */
/* @var $model common\models\SelectedBlog */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="selected-blog-form">

    <?php $form = ActiveForm::begin(); ?>

    <?php
	    $availablePost = [];
	    if(isset($model->post))
	    {
	        $availablePost[$model->post->id] = $model->post->title;
	    }

	    echo $form->field($model, 'post_id')->widget(SelectizeDropDownList::classname(), [
	        'loadUrl' => '/admin/post/blog-list',
	        'items' => $availablePost,
	        'options' => [
	            'multiple' => false,
	        ],
	        'clientOptions' => [
	            'delimiter' => ',',
	            'persist' => false,
	            'createOnBlur' => false,
	            'maxItems' => 1,
	            // 'create' => new JsExpression('function(input) { return { value: "{new}" + input, text: input } }'),
	        ],
	    ]);
    ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Добавить' : 'Изменить', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
