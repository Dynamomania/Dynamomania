<?php
use yii\helpers\Url;

/**
 * @var $this yii\web\View
 * @var $model common\models\Post
**/
$outDateLine = false;
$time = strtotime($model->created_at);
$date = date('d.m.Y', $time);
$time = strtotime($date);

if(abs(Yii::$app->session['news_post_time_last'] - $time) >= 60*60*24)
{
    Yii::$app->session['news_post_time_last'] = $time;
    $outDateLine = true;
}
?>

<?php if($outDateLine) { ?>
<div class="date">
    <div class="line"></div>
    <div class="day-month-year"><?= date('d.m.Y', strtotime($model->created_at)) ?></div>
    <div class="line"></div>
    <div class="clearfix"></div>
</div>
<?php } ?>

<div class="news-post">
    <div class="time"><?= date('H:i',strtotime($model->created_at)) ?></div>
    <a href="<?= $model->getUrl() ?>">
        <div class="title"><?= $model->title ?></div>
    </a>
    <div class="sub-part">
        <?php
            $image = $model->getAsset(\common\models\Asset::THUMBNAIL_NEWS);
            if (!empty($image->getFileUrl())) {
        ?>
        <div class="photo-img">
            <img src="<?= $image->getFileUrl() ?>">
        </div>
        <?php } ?>
        <div class="subtitle">
        <?= $model->getShortContent() ?>
        </div>
    </div>
    <?php if($model->comments_count > 0) { ?>
    <div class="news-comments-block">
        <div class="icon"></div>
        <div class="count"><?= $model->comments_count ?></div>
        <div class="clearfix"></div>
    </div>
    <?php } ?>
</div>