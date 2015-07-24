<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\MainInfo */

$this->title = 'Добавить информацию о команде';
$this->params['breadcrumbs'][] = ['label' => 'Информация о команде', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="main-info-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
