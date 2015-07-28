<?php
namespace frontend\controllers;

use Yii;
use common\models\Post;
use common\models\Tag;
use common\models\Tagging;
use common\models\Asset;
use common\models\Match;
use common\models\Team;
use common\models\Comment;
use common\models\Season;
use common\models\TransferType;
use common\models\Transfer;
use common\models\Tournament;
use common\models\Forward;
use common\models\Championship;
use common\models\CommentForm;
use common\models\SiteBlock;
use common\models\MatchEvent;
use common\models\Composition;
use common\models\Question;
use common\models\Claim;
use common\models\Contract;
use common\models\MainInfo;
use common\models\Player;
use common\models\Coach;
use common\models\TeamCoach;
use common\models\Album;
use common\models\Banner;
use common\models\VideoPost;
use common\models\Subscribing;
use common\models\Relation;


use frontend\models\ContactForm;
use common\models\Source;


use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\data\Pagination;
use yii\helpers\Json;
use yii\web\UploadedFile;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'image-upload' => [
                'class' => 'vova07\imperavi\actions\UploadAction',
                'url' => 'http://'.$_SERVER['HTTP_HOST'].'/images/store/post_attachments/', // Directory URL address, where files are stored.
                'path' => '@frontend/web/images/store/post_attachments' // Or absolute path to directory where files are stored.
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'foreColor' => '1667780',
                // 'minLength' => 6,
                // 'maxLength' => 6,
                'width' => 110,
                'height' => 45,
                // 'offset' => -2,
                // 'testLimit' => 3,
                // 'fixedVerifyCode' => 'Dynamomania',
            ],
        ];
    }

    /**
     * Main page
     * @return mixed Content
     */
    public function actionIndex()
    {
        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col3',
            'title' => '"Dynamomania.com" - сайт для болельщиков "Динамо" Киев',
            'columnFirst' => [
                'top3News' => SiteBlock::getTop3News(),
                'top6News' => SiteBlock::getTop6News(),
                'subscribing' => SiteBlock::getSubscribingForm(),
                'banner1' => SiteBlock::getBanner(Banner::REGION_FIRST_COLUMN),
                'blog_column' => SiteBlock::getBlogPosts(),
            ],
            'columnSecond' => [
                'slider_matches' => SiteBlock::getMatchesSlider(),
                'short_news' => SiteBlock::getShortNews(50, false),
            ],
            'columnThird' => [
                'questionBlock' => SiteBlock::getQuestionBlock(),
                'banner1' => SiteBlock::getBanner(Banner::REGION_THIRD_COLUMN),
                'tournament' => SiteBlock::getTournamentTable(),
                'reviewNews' => SiteBlock::getPhotoVideoNews(),
                'banner2' => SiteBlock::getBanner(Banner::REGION_THIRD_COLUMN),
                'top200tags' => SiteBlock::getTop200Tags(),                
                'banner3' => SiteBlock::getBanner(Banner::REGION_THIRD_COLUMN),
            ],
        ]);
    }

    /**
     * @param string $date Searching by date
     * @param string $t Tag name slug
     * @param string $q Search words
     * @return mixed Content
     */
    public function actionSearch($t = false, $q = false) 
    {
        $t = str_replace('-+-', '-#%#-', $t);
        $t = str_replace('+', ' ', $t);
        $t = str_replace('-#%#-', '+', $t);
        $postTable = Post::tableName();
        $query = Post::find()->where([
            'is_public' => 1, 
            // 'content_category_id' => Post::CATEGORY_BLOG,
        ]);

        if(isset($t) && trim($t) != '') {
            $taggingTable = Tagging::tableName();
            $tagTable = Tag::tableName();
            $query->innerJoin($taggingTable, "{$postTable}.id = {$taggingTable}.taggable_id");
            $query->innerJoin($tagTable, "{$taggingTable}.tag_id = {$tagTable}.id");
            $query->andWhere([
                "{$taggingTable}.taggable_type" => Tagging::TAGGABLE_POST,
                "{$tagTable}.name" => $t,
            ]);
        } elseif(isset($q) && trim($q) != '') {
            $search = addslashes($q);
            $query->andWhere("MATCH (content) AGAINST ('$search')");
        }
        $query->orderBy(["$postTable.created_at" => SORT_DESC]);

        if(!isset($_GET['page']) || $_GET['page'] == 1) {
            Yii::$app->session['news_post_time_last'] = 1;
        }
        $newsDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 15,
            ],
        ]);

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Поиск по сайту',
            'columnFirst' => [
                'news' => [
                    'view' => '@frontend/views/search/search_posts',
                    'data' => compact('newsDataProvider'),
                ],
            ],
            'columnSecond' => [
                'blog_column' => SiteBlock::getBlogPosts(),
            ],
        ]);
    }

    /**
     * @param string $date Searching by date
     * @return mixed Content
     */
    public function actionNews($date = null) 
    {
        $query = Post::find()->where(['is_public' => 1, 'content_category_id' => Post::CATEGORY_NEWS]);
        // check date
        if (strtotime($date) == null) {
            $date = false;
        } else {
            $parsed = date_parse($date);
            if (!checkdate($parsed["month"], $parsed["day"], $parsed["year"])) {
                $date = false;
            }
        }
        if(!empty($date))
        {
            $startDay = date("Y-m-d 00:00:00", 0);
            $endDay = date("Y-m-d 00:00:00", strtotime($date) + 60*60*24);
            $query->andFilterWhere(['between', 'created_at', $startDay, $endDay]);
            $query->orderBy(['created_at' => SORT_DESC]);
        } 
        else 
        {
            $query->orderBy(['created_at' => SORT_DESC]);
        }

        if(!isset($_GET['page']) || $_GET['page'] == 1) {
            Yii::$app->session['news_post_time_last'] = 1;
        }
        $newsDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 15,
            ],
        ]);

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Новости',
            'columnFirst' => [
                'calendar' => [
                    'view' => '@frontend/views/site/news_calendar',
                    'data' => compact('date'),
                ],
                'news' => [
                    'view' => '@frontend/views/site/news',
                    'data' => compact('newsDataProvider'),
                ],
            ],
            'columnSecond' => [
                'blog_column' => SiteBlock::getBlogPosts(),
            ],
        ]);
    }
    
    /**
     * @param int $id Post id
     * @param string $slug Post slug
     * @return mixed Content
     */
    public function actionPost($id, $slug) 
    {
        $post = Post::findOne($id);
        if (!isset($post)){
            throw new NotFoundHttpException('Страница не найдена.');
        }
        $image = $post->getAsset(Asset::THUMBNAIL_CONTENT);

        $options = [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$post->title,
            'columnFirst' => [
                'post' => [
                    'view' => '@frontend/views/site/post',
                    'data' => compact('post','image'),
                    'weight' => 0,
                ],
            ],
            'columnSecond' => [
                'short_news' => SiteBlock::getShortNews(20),
            ],

        ];

        if ($post->allow_comment) {
            // out all comments
            
            $options['columnFirst']['comments'] = Comment::getCommentsBlock($post->id, Comment::COMMENTABLE_POST);
            $options['columnFirst']['comments']['weight'] = 5;
        }
        usort($options['columnFirst'],'self::cmp');

        return $this->render('@frontend/views/site/index', $options);
    }

    /**
     * Adds a new comment
     * If adding is successful, the browser will be redirected to the 'previ' page.
     * 
     * @return mixed
     */
    public function actionCommentAdd() 
    {
        $model = new CommentForm();

        $out = ['success' => false];
        if ($model->load(Yii::$app->request->post())) {
            $model->user_id = Yii::$app->user->id;
            if($model->save()) {
                $out = [
                    'success' => true,
                    'newID' => $model->id,
                ];
            }
        }

        echo Json::encode($out);
    }
    
    /**
     * Adds a new comment
     * If adding is successful, the browser will be redirected to the 'previ' page.
     * 
     * @return mixed
     */
    public function actionMatches() 
    {
        //select teams of interest for page matches sort
        $selectTeamsOI = [
            Team::TEAM_DK_FIRST_FULL_NAME => Team::findOne(Team::TEAM_DK_FIRST_FULL_NAME),
            Team::TEAM_DK_M => Team::findOne(Team::TEAM_DK_M),
            Team::TEAM_DK2 => Team::findOne(Team::TEAM_DK2),
            Team::TEAM_U19 => Team::findOne(Team::TEAM_U19),
            Team::TEAM_UKRAINE => Team::findOne(Team::TEAM_UKRAINE),
        ];        

        if (isset($_GET['team'])) {
            $activeTeam = $_GET['team'];
        }
        else {
            $activeTeam = Team::TEAM_DK_FIRST_FULL_NAME;
        }

        //select seasons
        $seasons = Season::find()
            ->innerJoinWith('matches')
            ->orderBy(['id' => SORT_DESC])
            ->all();

        if (isset($_GET['season'])) {
            $activeSeason = $_GET['season'];
        }
        else {
            $activeSeasonObj = array_values($seasons)[0];
            $activeSeason = $activeSeasonObj->id;
        }

        //select tournaments
        $tableTournament = Championship::tableName();
        $tableMatch = Match::tableName();

        $tournaments = Championship::find()
            ->innerJoin($tableMatch, "{$tableMatch}.championship_id = {$tableTournament}.id")
            ->where(['is_visible' => 1])
            ->andWhere(['or', ["{$tableMatch}.command_home_id" => $activeTeam], ["{$tableMatch}.command_guest_id" => $activeTeam]])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $query = NULL;     

        if (isset($_GET['championship'])) {
            $activeTournament = $_GET['championship'];
            if ($_GET['championship'] == 'all-tournaments') {
                $activeTournament = NULL;
            }
        }
        else {
            $activeTournament = NULL;
        }

        $query = Match::find()
        ->where(['is_visible' => 1, 'season_id' => $activeSeason])
        ->andWhere(['or', ['command_home_id' => $activeTeam], ['command_guest_id' => $activeTeam]]);

        if (isset($activeTournament)) {
            $query->andWhere(['championship_id' => $activeTournament]);
        }

        $query->orderBy(['date' => SORT_DESC]);
        
        $matchDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 100,
            ],
        ]);
        
        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Матчи',
            'columnFirst' => [
                'matches' => [
                    'view' => '@frontend/views/site/matches',
                    'data' => compact('matchDataProvider', 
                                      'selectTeamsOI', 
                                      'activeTeam', 
                                      'seasons', 
                                      'activeSeason', 
                                      'tournaments', 
                                      'activeTournament'),
                ],
            ],
            'columnSecond' => [  
                'tournament' => SiteBlock::getShortNews(20),
            ],
        ]);
    }

    /**
     * Transfers page
     * 
     * @return mixed
     */
    public function actionTransfers() 
    {
        // transfer type select
        $transferTypes = TransferType::find()->orderBy(['id' => SORT_DESC])->all();
        $activeTransferType = 'all-types';
        if(isset($_GET['transfer-type'])) {
            foreach ($transferTypes as $transferType) {
                if($_GET['transfer-type'] == $transferType->id){
                    $activeTransferType = $_GET['transfer-type'];
                } 
            }
        }
        $transferTypesData = [
            'all-types' => ['value' => 'all-types', 'text' => 'Все трансферы', 'active' => false],
        ];
        foreach ($transferTypes as $transferType) {
            $transferTypesData[$transferType->id] = [
                'value' => $transferType->id,
                'text' => $transferType->name,
                'active' => false,
            ];
        }
        $transferTypesData[$activeTransferType]['active'] = true;
        foreach ($transferTypesData as $key => $transferType) {
            $transferTypesData[$key] = (object) $transferType;
        }

        // season select
        $seasons = Season::find()
            ->innerJoinWith('transfers')
            ->orderBy(['id' => SORT_DESC])
            ->all();
        
        $firstSeasonObj = array_values($seasons)[0];
        $firstSeasonId = $firstSeasonObj->id;
        $activeSeason = $firstSeasonId;
        if(isset($_GET['season'])) {
            foreach ($seasons as $season) {
                if($_GET['season'] == $season->id){
                    $activeSeason = $_GET['season'];
                } 
            }
        }
        $seasonsData = [];
        foreach ($seasons as $season) {
            $seasonName = $season->name;
            if($season->window == $season::WINDOW_WINTER) {
                $seasonName .= ' Зимнее окно';
            } else {
                $seasonName .= ' Летнее окно';
            }
            $seasonsData[$season->id] = [
                'value' => $season->id,
                'text' => $seasonName,
                'active' => false,
            ];
        }
        $seasonsData[$activeSeason]['active'] = true;
        foreach ($seasonsData as $key => $season) {
            $seasonsData[$key] = (object) $season;
        }

        // transfers tables
        $transferQuery = Transfer::find()
            ->where(['season_id' => $activeSeason])
            ->orderBy(['created_at' => SORT_ASC]);
        if($activeTransferType != 'all-types') {
            $transferQuery->andWhere(['transfer_type_id' => $activeTransferType]);
        }
        $transfers = $transferQuery->all();


        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Трансферы',
            'columnFirst' => [
                'transfers' => [
                    'view' => '@frontend/views/transfers/transfers',
                    'data' => compact('transferTypesData', 'seasonsData', 'transfers'),
                ],
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(),
            ],
        ]);
    }

    /**
     * Transfers page
     * 
     * @param int $id Transfer id
     * @return mixed
     */
    public function actionTransfer($id) 
    {
        $transfer = Transfer::findOne($id);
        
        if(!isset($transfer)) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Трансферы | '.$transfer->player->name,
            'columnFirst' => [
                'transfers' => [
                    'view' => '@frontend/views/transfers/transfer_single',
                    'data' => compact('transfer'),
                ],
                'comments' => Comment::getCommentsBlock($transfer->id, Comment::COMMENTABLE_TRANSFER),
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(10),
                // 'blogs' => SiteBlock::getBlogPosts(),
                // banners 3
            ],
        ]);
    }

    /**
     * Tournament page
     * 
     * @return mixed
     */
    public function actionTournament()
    {
        $tournamentTable = Tournament::tableName();
        $championshipTable = Championship::tableName();
        $seasonTable = Season::tableName();        

        // championship type select
        $championships = Championship::find()
            ->innerJoin($tournamentTable, "{$tournamentTable}.championship_id = {$championshipTable}.id")
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $firstChampionshipObj = array_values($championships)[0];
        $firstChampionshipId = $firstChampionshipObj->id;
        $activeChampionship = $firstChampionshipId;
        if(isset($_GET['championship'])) {
            foreach ($championships as $championship) {
                if($_GET['championship'] == $championship->id){
                    $activeChampionship = $_GET['championship'];
                } 
            }
        }
        $championshipsData = [];
        foreach ($championships as $championship) {
            $championshipsData[$championship->id] = [
                'value' => $championship->id,
                'text' => $championship->name,
                'active' => false,
            ];
        }
        $championshipsData[$activeChampionship]['active'] = true;
        foreach ($championshipsData as $key => $championship) {
            $championshipsData[$key] = (object) $championship;
        }

        // season select
        $seasons = Season::find()
            ->innerJoinWith('tournaments')
            ->orderBy(['id' => SORT_DESC])
            ->all();
        
        $firstSeasonObj = array_values($seasons)[0];
        $firstSeasonId = $firstSeasonObj->id;
        $activeSeason = $firstSeasonId;
        if(isset($_GET['season'])) {
            foreach ($seasons as $season) {
                if($_GET['season'] == $season->id){
                    $activeSeason = $_GET['season'];
                } 
            }
        }
        $seasonsData = [];
        foreach ($seasons as $season) {
            $seasonName = $season->name;
            $seasonsData[$season->id] = [
                'value' => $season->id,
                'text' => $seasonName,
                'active' => false,
            ];
        }
        $seasonsData[$activeSeason]['active'] = true;
        foreach ($seasonsData as $key => $season) {
            $seasonsData[$key] = (object) $season;
        }

        $tournamentData = Tournament::find()
            ->where([
                'season_id' => $activeSeason,
                'championship_id' => $activeChampionship,
            ])->orderBy(['points' => SORT_DESC])
            ->all();

        $tournamentData = Tournament::sort($tournamentData);

        $forwards = Forward::find()
            ->where([
                'season_id' => $activeSeason,
            ])->orderBy([
                'goals' => SORT_DESC,
                'penalty' => SORT_DESC,
            ])->all();

        $options = [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Турнирная таблица',
            'columnFirst' => [
                'tournament' => [
                    'view' => '@frontend/views/tournament/tournament_full',
                    'data' => compact('tournamentData', 'championshipsData', 'seasonsData'),
                ],
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(20),
            ],
        ];

        if(count($forwards) > 0) {
            $options['columnFirst']['forwards'] = [
                'view' => '@frontend/views/tournament/forwards',
                'data' => compact('forwards'),
            ];
        }

        return $this->render('@frontend/views/site/index', $options);
    }

    /**
     * Translation page
     * 
     * @param int $id Match id
     * @return mixed
     */
    public function actionMatchTranslation($id) 
    {
        $match = Match::findOne($id);
        
        if(!isset($match)) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        $title = "Онлайн трансляция матча ".$match->teamHome->name." - ".$match->teamGuest->name;

        $matchEvents = MatchEvent::find()
            ->where(['match_id' => $match->id])
            ->all();

        $teamPlayers = Composition::getSquadSort($match->id);

        $teamHomePlayers = array();
        $teamGuestPlayers = array();
        foreach ($teamPlayers as $player) {
            if($player->command_id == $match->command_home_id) {
                array_push($teamHomePlayers, $player);
            } else {
                array_push($teamGuestPlayers, $player);
            }
        }

        // Disable banners if match is online 
        if($match->is_visible && !$match->is_finished && strtotime($match->date) < time())
        {
             SiteBlock::$banners = [false];
        }

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '. $title,
            'columnFirst' => [
                'menu' => [
                    'view' => '@frontend/views/translation/menu',
                    'data' => compact('match'),
                ],
                'translation' => [
                    'view' => '@frontend/views/translation/index',
                    'data' => compact('match', 'matchEvents', 'teamHomePlayers', 'teamGuestPlayers'),
                ],
                'comments' => Comment::getCommentsBlock($match->id, Comment::COMMENTABLE_MATCH),
            ],
            'columnSecond' => [ 
                'tournament' => SiteBlock::getTournamentTable(),
                'short_news' => SiteBlock::getShortNews(20),
            ],
        ]);
    }

    /**
     * All tags page
     *     
     * @return mixed
     */
    public function actionTags() 
    {
        $tags = Tag::find()->all();
        
        if(!isset($tags)) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        $title = "Все теги";

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$title,
            'columnFirst' => [
                'allTags' => [
                    'view' => '@frontend/views/site/tags',
                    'data' => compact('tags'),
                ],                
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(),
            ],
        ]);
    }

    /**
     * Translation page
     * 
     * @return mixed
     */
    public function actionMatchProtocol($id) 
    {
        $match = Match::findOne($id);
        
        if(!isset($match)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $title = "Протокол матча ".$match->teamHome->name." - ".$match->teamGuest->name;

        $matchEvents = MatchEvent::find()
            ->where(['match_id' => $match->id])
            ->all();

        $teamPlayers = Composition::find()
            ->where([
                'match_id' => $match->id, 
            ])
            ->all();

        $teamPlayers = Composition::sortPlayers($teamPlayers);

        $teamHomePlayers = [];
        $teamGuestPlayers = [];
        foreach ($teamPlayers as $player) {
            if($player->command_id == $match->command_home_id) {
                $teamHomePlayers[] = $player;
            } else {
                $teamGuestPlayers[] = $player;
            }
        }

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$title,
            'columnFirst' => [
                'menu' => [
                    'view' => '@frontend/views/translation/menu',
                    'data' => compact('match'),
                ],
                'protocol' => [
                    'view' => '@frontend/views/translation/protocol',
                    'data' => compact('match', 'matchEvents', 'teamHomePlayers', 'teamGuestPlayers'),
                ],
                'comments' => Comment::getCommentsBlock($match->id, Comment::COMMENTABLE_MATCH),
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(20),
            ],
        ]);
    }

    /**
     * Match news page
     * 
     * @return mixed
     */
    public function actionMatchNews($id) 
    {
        $match = Match::findOne($id);
        
        if(!isset($match)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $title = "Материалы по матчу ".$match->name;
        
        $postTable = Post::tableName();
        $relationTable = Relation::tableName();

        $query = Post::find()
            ->innerJoin($relationTable, "{$postTable}.id = {$relationTable}.relationable_id")
            ->where([
                'is_public' => 1,
                "{$relationTable}.relation_type_id" => Relation::RELATION_NEWS,
                "{$relationTable}.relationable_type" => Relation::RELATIONABLE_POST,
                "{$relationTable}.parent_id" => $match->id,
            ]);

        if(!isset($_GET['page']) || $_GET['page'] == 1) {
            Yii::$app->session['news_post_time_last'] = 1;
        }
        $postsDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$title,
            'columnFirst' => [
                'menu' => [
                    'view' => '@frontend/views/translation/menu',
                    'data' => compact('match'),
                ],
                'translation' => [
                    'view' => '@frontend/views/site/news',
                    'data' => [
                        'newsDataProvider' => $postsDataProvider,
                    ],
                ],
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(20),
            ],
        ]);
    }

    /**
     * Match report page
     * 
     * @return mixed
     */
    public function actionMatchReport($id) 
    {
        $match = Match::findOne($id);
        
        if(!isset($match)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $postTable = Post::tableName();
        $relationTable = Relation::tableName();

        $post = Post::find()
            ->innerJoin($relationTable, "{$postTable}.id = {$relationTable}.relationable_id")
            ->where([
                'is_public' => 1,
                "{$relationTable}.relation_type_id" => Relation::RELATION_REPORT,
                "{$relationTable}.relationable_type" => Relation::RELATIONABLE_POST,
                "{$relationTable}.parent_id" => $match->id,
            ])->one();
        
        $options = [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Отчет по матчу: '.$match->name,
            'columnFirst' => [
                'menu' => [
                    'view' => '@frontend/views/translation/menu',
                    'data' => compact('match'),
                ],
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(20),
            ],
        ];

        if(isset($post)) {
            $image = $post->getAsset(Asset::THUMBNAIL_CONTENT);
            
            $options['columnFirst']['post'] = [
                'view' => '@frontend/views/site/post',
                'data' => compact('post','image'),
                'weight' => 3,
            ];
            if ($post->allow_comment) {
                $options['columnFirst']['comments'] = Comment::getCommentsBlock($post->id, Comment::COMMENTABLE_POST);
                $options['columnFirst']['comments']['weight'] = 5;
            }
        } else {
            $options['columnFirst']['post'] = [
                'view' => '@frontend/views/site/empty',
                'data' => [],
                'weight' => 3,
            ];
        }
        usort($options['columnFirst'],'self::cmp');

        return $this->render('@frontend/views/site/index', $options);
    }

    /**
     * Match photos page
     * @param $id Match id
     * @return mixed
     */
    public function actionMatchPhotos($id) 
    {
        $match = Match::findOne($id);
        
        if(!isset($match)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $title = "Фото матча ".$match->name;

        $albumTable = Album::tableName();
        $relationTable = Relation::tableName();

        $album = Album::find()
            ->innerJoin($relationTable, "{$albumTable}.id = {$relationTable}.relationable_id")
            ->where([
                'is_public' => 1,
                "{$relationTable}.relationable_type" => Relation::RELATIONABLE_ALBUM,
                "{$relationTable}.parent_id" => $match->id,
            ])->one();
        
        $columnData = [];
        $columnData['menu'] = [
            'view' => '@frontend/views/translation/menu',
            'data' => compact('match'),
        ];

        if (!isset($album)){
            $columnData['content'] = [
                'view' => '@frontend/views/site/empty',
                'data' => ['message' => 'Фотографий к матчу не найдено'],
                'weight' => 10,
            ];
        } else {
            $contentImagesCount = Asset::find()
                ->where([
                    'assetable_id' => $album->id,
                    'assetable_type' => Asset::ASSETABLE_ALBUM,
                    'thumbnail' => Asset::THUMBNAIL_CONTENT,
                ])->count();
            if($contentImagesCount > 0) {
                $imageCount = $contentImagesCount;
                $bigImages = Asset::find()
                    ->where([
                        'assetable_id' => $album->id,
                        'assetable_type' => Asset::ASSETABLE_ALBUM,
                        'thumbnail' => Asset::THUMBNAIL_CONTENT,
                    ])
                    ->limit(12)
                    ->offset(0)
                    ->orderBy(['id' => SORT_ASC])
                    ->all();
                $smallImages = Asset::find()
                    ->where([
                        'assetable_id' => $album->id,
                        'assetable_type' => Asset::ASSETABLE_ALBUM,
                        'thumbnail' => Asset::THUMBNAIL_SMALL,
                    ])
                    ->limit(12)
                    ->offset(0)
                    ->orderBy(['id' => SORT_ASC])
                    ->all();
            } else {
                $imageCount = Asset::find()
                    ->where([
                        'assetable_id' => $album->id,
                        'assetable_type' => Asset::ASSETABLE_ALBUM,
                        'parent_id' => NULL,
                    ])->count();
                $bigImages = Asset::find()
                    ->where([
                        'assetable_id' => $album->id,
                        'assetable_type' => Asset::ASSETABLE_ALBUM,
                    ])
                    ->limit(12)
                    ->offset(0)
                    ->orderBy(['id' => SORT_ASC])
                    ->all();
                $smallImages = $bigImages;
            }

            $columnData['content'] = [
                'view' => '@frontend/views/site/album',
                'data' => compact('album', 'bigImages', 'smallImages', 'imageCount'),
                'weight' => 10,
            ];
            $columnData['comments'] = Comment::getCommentsBlock($album->id, Comment::COMMENTABLE_ALBUM);
            $columnData['comments']['weight'] = 20;
        }
        usort($columnData, 'self::cmp');

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$title,
            'columnFirst' => $columnData,
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(20),
            ],
        ]);
    }

    /**
     * Match videos page
     * @param $id Match id
     * @return mixed
     */
    public function actionMatchVideos($id) 
    {
        $match = Match::findOne($id);
        
        if(!isset($match)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $title = "Видео матча ".$match->name;

        $videoPostTable = VideoPost::tableName();
        $relationTable = Relation::tableName();

        $query = VideoPost::find()
            ->innerJoin($relationTable, "{$videoPostTable}.id = {$relationTable}.relationable_id")
            ->where([
                'is_public' => 1,
                "{$relationTable}.relationable_type" => Relation::RELATIONABLE_VIDEO,
                "{$relationTable}.parent_id" => $match->id,
            ])->orderBy(['created_at' => SORT_DESC]);

        $videosDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);
        $emptyText = 'Видеозаписей к матчу не найдено';

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$title,
            'columnFirst' => [
                'menu' => [
                    'view' => '@frontend/views/translation/menu',
                    'data' => compact('match'),
                ],
                'content' => [
                    'view' => '@frontend/views/site/videos',
                    'data' => compact('videosDataProvider', 'emptyText'),
                ],
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(20),
            ],
        ]);
    }

    /**
     * Inquirers page
     * 
     * @return mixed
     */
    public function actionInquirers() 
    {
        $query = Question::find()
            ->where([
                'parent_id' => null,
            ])->orderBy(['created_at' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 12,
            ],
        ]);

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Все опросы',
            'columnFirst' => [
                'inquirers' => [
                    'view' => '@frontend/views/site/inquirers',
                    'data' => compact('dataProvider'),
                ],
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(),
            ],
        ]);
    }

    /**
     * Inquirer page
     * 
     * @param int $id Inquirer id
     * @return mixed
     */
    public function actionInquirerPage($id)
    {        
        if(!isset($id)) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Опрос',
            'columnFirst' => [
                'inquierer' => SiteBlock::getQuestionBlockTitle(false, $id),
                'comments' => Comment::getCommentsBlock($id, Comment::COMMENTABLE_INQUIRER),
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(10)
            ],
        ]);
    }

    /**
     * Player page
     * 
     * @param int $id Player id
     * @return mixed
     */
    public function actionPlayer($id, $slug) 
    {  
        $player = Player::findOne($id);

        if(!isset($player)) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        $image = $player->getAsset(Asset::THUMBNAIL_CONTENT);

        $options = [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$player->name,
            'columnFirst' => [
                'post' => [
                    'view' => '@frontend/views/site/team_member',
                    'data' => compact('player','image'),
                    'weight' => 0,
                ],
            ],
            'columnSecond' => [
                'blog_column' => SiteBlock::getBlogPosts(),
            ],

        ];

        return $this->render('@frontend/views/site/index', $options);
    }

    /**
     * Player page
     * 
     * @param int $id Player id
     * @return mixed
     */
    public function actionCoach($id, $slug) 
    {  
        $coach = Coach::findOne($id);

        if(!isset($coach)) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        $image = $coach->getAsset(Asset::THUMBNAIL_CONTENT);

        $options = [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$coach->name,
            'columnFirst' => [
                'post' => [
                    'view' => '@frontend/views/site/coach',
                    'data' => compact('coach','image'),
                    'weight' => 0,
                ],
            ],
            'columnSecond' => [
                'blog_column' => SiteBlock::getBlogPosts(),
            ],

        ];

        return $this->render('@frontend/views/site/index', $options);
    }

    /**
     * Claim page
     * 
     * @param int $id Comment id
     * @return mixed
     */
    public function actionComplain($id) 
    {
        $comment = Comment::findOne($id);
        
        if(!isset($comment)) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        if(Yii::$app->user->isGuest) {
            throw new BadRequestHttpException('Для отправки жалобы авторизируйтесь.');
        }

        $claim = Claim::find()
            ->where([
                'comment_id' => $comment->id,
                'user_id' => Yii::$app->user->id,
            ])
            ->one();

        if(!isset($claim->id)) {
            $claim = new Claim();
            $claim->comment_id = $comment->id;
            $claim->comment_author = $comment->user->id;
            $claim->user_id = Yii::$app->user->id;
        }

        if($claim->load(Yii::$app->request->post())){
            $claim->save();
        }

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Жалоба на комментарий',
            'columnFirst' => [
                'claim' => [
                    'view' => '@frontend/views/forms/complain_form',
                    'data' => compact('comment', 'claim'),
                ],
            ],
            'columnSecond' => [ 
                'photo_news' => SiteBlock::getPhotoNews(),
            ],
        ]);
    }

    /**
     * Team full info page
     * @param $id int Team id
     * @param $tab string Team id
     * @return mixed
     */
    public function actionTeam($tab, $id = false) 
    {
        if($id === false) $id = Team::TEAM_DK_FIRST_FULL_NAME;
        $team = Team::findOne($id);
        $tabs = ['info', 'composition', 'achievements', 'record-holders'];
        if(!isset($team) || !in_array($tab, $tabs)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        if($tab == 'composition') {
            $availableTeams = [
                Team::TEAM_DK_FIRST_FULL_NAME => Team::findOne(Team::TEAM_DK_FIRST_FULL_NAME),
                Team::TEAM_DK_M => Team::findOne(Team::TEAM_DK_M),
                Team::TEAM_DK2 => Team::findOne(Team::TEAM_DK2),
                Team::TEAM_U19 => Team::findOne(Team::TEAM_U19),
            ];   

            $seasonTable = Season::tableName();
            $availableSeasons = Season::find()
                ->innerJoinWith('contracts')
                ->where(['window' => Season::WINDOW_WINTER])
                ->andWhere(["command_id" => $id])
                ->orderBy(["$seasonTable.id" => SORT_DESC])
                ->all();
            $availableSeasonsIds = [];
            foreach ($availableSeasons as $season) {
                $availableSeasonsIds[] = $season->id;
            }
            if (isset($_GET['season']) && in_array($_GET['season'], $availableSeasonsIds)) {
                $activeSeason = $_GET['season'];
            }
            else {
                $activeSeason = $availableSeasonsIds[0];
            }

            $composition = Contract::find()
                ->where([
                    'season_id' => $activeSeason,   
                    'command_id' => $team->id,   
                ])
                ->orderBy(['amplua_id' => SORT_ASC])
                ->all();

            $mainCoach = TeamCoach::find()
                ->where([
                    'is_main' => 1,
                    'season_id' => $activeSeason,
                    'team_id' => $id,
                ])->one();

            $teamCoaches = TeamCoach::find()
                ->where([
                    'season_id' => $activeSeason,
                    'team_id' => $id,
                    'is_main' => 0,
                ])->all();
            
            $data = [
                'teamModel' => $team,
                'availableSeasons' => $availableSeasons,
                'activeSeason' => $activeSeason,
                'availableTeams' => $availableTeams,
                'activeTeam' => $team->id,
                'composition' => $composition,
                'mainCoach' => $mainCoach,
                'teamCoaches' => $teamCoaches,
            ];
        } else {
            $information = MainInfo::find()->all();
            $info = [];
            foreach ($information as $data) {
                $info[$data->name] = $data;
            }
            $data = compact('team', 'info');
        }

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$team->name,
            'columnFirst' => [
                'navbar' => [
                    'view' => '@frontend/views/team/menu',
                    'data' => compact('team', 'tab'),
                ],
                'content' => [
                    'view' => '@frontend/views/team/tab-'.$tab,
                    'data' => $data,
                ],
            ],
            'columnSecond' => [ 
                'tournament' => SiteBlock::getShortNews(20),
            ],
        ]);
    }

    /**
     * Add post page
     * 
     * @return mixed
     */
    public function actionPostAdd() 
    {
        if(Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException("Вы не можете выполнить это действие.");
        }

        $model = new Post();
        $model->content_category_id = Post::CATEGORY_BLOG;
        $model->tags = [];
        
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {

            $model->allow_comment = 1;
            $model->is_public = 1;
            $model->content_category_id = Post::CATEGORY_BLOG;
            $model->user_id = Yii::$app->user->id;

            // Set slug
            $model->slug = $model->genSlug($model->title);

            // Save the model to have a record number
            if($model->save())
            {
                // Adding new tags
                if(is_array($model->tags))
                {
                    foreach ($model->tags as $id) {
                        $model->addTag($id);
                    }
                }

                $cached_tag_list = [];
                $newTags = $model->getTags();
                foreach ($newTags as $newTag) {
                    $cached_tag_list[] = $newTag->name;
                }
                $model->cached_tag_list = implode(', ', $cached_tag_list);

                // Set image
                $uploadedFile = UploadedFile::getInstance($model, 'image');
                if($uploadedFile)
                {
                    // Save origionals 
                    $asset = new Asset();
                    $asset->assetable_type = Asset::ASSETABLE_POST;
                    $asset->assetable_id = $model->id;
                    $asset->uploadedFile = $uploadedFile;
                    $asset->saveAsset();

                    // Save thumbnails 
                    $imageID = $asset->id;
                    $thumbnails = Asset::getThumbnails(Asset::ASSETABLE_POST);

                    foreach ($thumbnails as $thumbnail) {
                        $asset = new Asset();
                        $asset->parent_id = $imageID;
                        $asset->thumbnail = $thumbnail;
                        $asset->assetable_type = Asset::ASSETABLE_POST;
                        $asset->assetable_id = $model->id;
                        $asset->uploadedFile = $uploadedFile;
                        $asset->saveAsset();
                    }
                }
                $model->save(false);
                return $this->redirect($model->getUrl());
            }
            var_dump($model->getErrors());
            die;
        } 
        $title = 'Добавить запись в блог';
        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$title,
            'columnFirst' => [
                'blog_form' => [
                    'view' => '@frontend/views/forms/blog_form',
                    'data' => compact('model', 'tags', 'title'),
                ],
            ],
            'columnSecond' => [ 
                'blogs' => SiteBlock::getBlogPosts(), // 3 blogs
            ],
        ]);
    }

    /**
     * Edit post page
     * 
     * @param $id int Post id
     * @return mixed
     */
    public function actionPostEdit($id) 
    {
        $model = Post::findOne($id);
        if (!isset($model)){
            throw new NotFoundHttpException('Страница не найдена.');
        }

        if($model->content_category_id != Post::CATEGORY_BLOG || 
            empty(Yii::$app->user) || $model->user_id != Yii::$app->user->id) {
            throw new ForbiddenHttpException("Вы не можете выполнить это действие.");
        }

        $image = $model->getAsset();
        $tags = $model->getTags();
        $assets = $model->getAssets();
        $model->tags = [];
        foreach ($tags as $tag) {
            $model->tags[] = $tag->id;
        }

        $model->title = html_entity_decode($model->title);
        $model->content = html_entity_decode($model->content);

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {

            // Set slug
            $model->slug = $model->genSlug($model->title);

            // Set image
            $uploadedFile = UploadedFile::getInstance($model, 'image');
            if($uploadedFile)
            {
                // Remove old assets
                foreach ($assets as $asset) {
                    $asset->delete();
                }

                // Save origionals 
                $asset = new Asset();
                $asset->assetable_type = Asset::ASSETABLE_POST;
                $asset->assetable_id = $model->id;
                $asset->uploadedFile = $uploadedFile;
                $asset->saveAsset();

                // Save thumbnails 
                $imageID = $asset->id;
                $thumbnails = Asset::getThumbnails(Asset::ASSETABLE_POST);

                foreach ($thumbnails as $thumbnail) {
                    $asset = new Asset();
                    $asset->parent_id = $imageID;
                    $asset->thumbnail = $thumbnail;
                    $asset->assetable_type = Asset::ASSETABLE_POST;
                    $asset->assetable_id = $model->id;
                    $asset->uploadedFile = $uploadedFile;
                    $asset->saveAsset();
                }
            }

            $existingTags = [];
            // Remove tags
            foreach ($tags as $tag) {
                if(!is_array($model->tags) || !in_array($tag->id, $model->tags)) {
                    $model->removeTag($tag->id);
                } else $existingTags[] = $tag->id;
            }
            // Adding new tags
            if(is_array($model->tags))
            {
                foreach ($model->tags as $id) {
                    if(!in_array($id, $existingTags)) {
                        $model->addTag($id);
                    }
                }
            }

            $cached_tag_list = [];
            $newTags = $model->getTags();
            foreach ($newTags as $newTag) {
                $cached_tag_list[] = $newTag->name;
            }
            $model->cached_tag_list = implode(', ', $cached_tag_list);

            $model->save();
            return $this->redirect($model->getUrl());
        } 
        $title = 'Изменить запись в блоге';
        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | '.$title,
            'columnFirst' => [
                'blog_form' => [
                    'view' => '@frontend/views/forms/blog_form',
                    'data' => compact('model', 'tags', 'image', 'title'),
                ],
            ],
            'columnSecond' => [ 
                'blogs' => SiteBlock::getBlogPosts(), // 3 blogs
            ],
        ]);
    }

    /**
     * Blogs page
     * @param $id int User id
     * @return mixed
     */
    public function actionBlogs($id = false) 
    {
        $query = Post::find()
            ->where([
                'is_public' => 1,
                'content_category_id' => Post::CATEGORY_BLOG,
            ]);
        if($id) {
            $query->andWhere(['user_id' => $id]);
        }
        $query->orderBy(['created_at' => SORT_DESC]);

        $postsDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Блоги',
            'columnFirst' => [
                'content' => [
                    'view' => '@frontend/views/site/blogs',
                    'data' => compact('postsDataProvider'),
                ],
            ],
            'columnSecond' => [
                'bestblogs' => SiteBlock::getBlogPostsByRating(),
                'short_news' => SiteBlock::getShortNews(),
            ],
        ]);
    }

    /**
     * Info page
     * @return mixed
     */
    public function actionInformation()
    {
        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Информация о сайте',
            'columnFirst' => [
                'content' => [
                    'view' => '@frontend/views/site/information',
                ],
            ],
            'columnSecond' => [
                // 'reviewNews' => SiteBlock::getPhotoVideoNews(),
            ],
        ]);
    }

    /**
     * Contscts page
     * @return mixed
     */
    public function actionContacts()
    {
        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Контакты',
            'columnFirst' => [
                'content' => [
                    'view' => '@frontend/views/site/contacts',
                ],
            ],
            'columnSecond' => [
                // 'reviewNews' => SiteBlock::getPhotoVideoNews(),
            ],
        ]);
    }

    /**
     * Photo page
     * @return mixed
     */
    public function actionPhotos() 
    {
        $query = Album::find()
            ->where([
                'is_public' => 1,
            ]);
        $query->orderBy(['created_at' => SORT_DESC]);

        $albumsDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Фото',
            'columnFirst' => [
                'content' => [
                    'view' => '@frontend/views/site/photos',
                    'data' => compact('albumsDataProvider'),
                ],
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(),
            ],
        ]);
    }

    /**
     * Video page
     * @return mixed
     */
    public function actionVideos() 
    {
        $query = VideoPost::find()
            ->where([
                'is_public' => 1,
            ]);
        $query->orderBy(['created_at' => SORT_DESC]);

        $videosDataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Видео',
            'columnFirst' => [
                'content' => [
                    'view' => '@frontend/views/site/videos',
                    'data' => compact('videosDataProvider'),
                ],
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(),
            ],
        ]);
    }

    /**
     * Video page
     * @param int $id Album id
     * @param string $slug Album slug
     * @return mixed
     */
    public function actionVideoPost($id, $slug) 
    {
        $videoPost = VideoPost::find()
            ->where([
                'id' => $id,
                'is_public' => 1,
            ])->one();

        if (!isset($videoPost)){
            throw new NotFoundHttpException('Страница не найдена.');
        }

        $image = $videoPost->getAsset();
        $video = $videoPost->getVideoAsset();

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Видео: '.$videoPost->title,
            'columnFirst' => [
                'content' => [
                    'view' => '@frontend/views/site/video_post',
                    'data' => compact('videoPost', 'image', 'video'),
                ],
                'comments' => Comment::getCommentsBlock($videoPost->id, Comment::COMMENTABLE_VIDEO),
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(),
            ],
        ]);
    }


    /**
     * Album page with slider
     * @param int $id Album id
     * @param string $slug Album slug
     * @return mixed Content
     */
    public function actionAlbum($id, $slug) 
    {
        $album = Album::find()
            ->where([
                'id' => $id,
                'is_public' => 1,
            ])->one();

        if (!isset($album)){
            throw new NotFoundHttpException('Страница не найдена.');
        }

        $contentImagesCount = Asset::find()
            ->where([
                'assetable_id' => $id,
                'assetable_type' => Asset::ASSETABLE_ALBUM,
                'thumbnail' => Asset::THUMBNAIL_CONTENT,
            ])->count();
        if($contentImagesCount > 0) {
            $imageCount = $contentImagesCount;
            $bigImages = Asset::find()
                ->where([
                    'assetable_id' => $id,
                    'assetable_type' => Asset::ASSETABLE_ALBUM,
                    'thumbnail' => Asset::THUMBNAIL_CONTENT,
                ])
                ->limit(12)
                ->offset(0)
                ->orderBy(['id' => SORT_ASC])
                ->all();
            $smallImages = Asset::find()
                ->where([
                    'assetable_id' => $id,
                    'assetable_type' => Asset::ASSETABLE_ALBUM,
                    'thumbnail' => Asset::THUMBNAIL_SMALL,
                ])
                ->limit(12)
                ->offset(0)
                ->orderBy(['id' => SORT_ASC])
                ->all();
        } else {
            $imageCount = Asset::find()
                ->where([
                    'assetable_id' => $id,
                    'assetable_type' => Asset::ASSETABLE_ALBUM,
                    'parent_id' => NULL,
                ])->count();
            $bigImages = Asset::find()
                ->where([
                    'assetable_id' => $id,
                    'assetable_type' => Asset::ASSETABLE_ALBUM,
                ])
                ->limit(12)
                ->offset(0)
                ->orderBy(['id' => SORT_ASC])
                ->all();
            $smallImages = $bigImages;
        }

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Фотоальбом: '.$album->title,
            'columnFirst' => [
                'content' => [
                    'view' => '@frontend/views/site/album',
                    'data' => compact('album', 'bigImages', 'smallImages', 'imageCount'),
                ],
                'comments' => Comment::getCommentsBlock($album->id, Comment::COMMENTABLE_ALBUM),
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(20), 
            ],
        ]);
    }

    /**
     * Album page with slider
     * @param int $id Album id
     * @param int $count Exist amount of images
     * @return mixed Content
     */
    public function actionAlbumLoadImages($id, $count) 
    {
        $album = Album::findOne($id);

        $contentImagesCount = Asset::find()
            ->where([
                'assetable_id' => $id,
                'assetable_type' => Asset::ASSETABLE_ALBUM,
                'thumbnail' => Asset::THUMBNAIL_CONTENT,
            ])->count();
        $query = Asset::find()
            ->where([
                'assetable_id' => $id,
                'assetable_type' => Asset::ASSETABLE_ALBUM,
            ]);
        if($contentImagesCount > 0) {
            $query->andWhere([
                'thumbnail' => Asset::THUMBNAIL_CONTENT,
            ]);
        } 
        $images = $query->limit(12)
            ->offset($count)
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $thumbnailImages = [];
        $contentImagesHtml = '';
        $thumbnailImagesHtml = '';
        foreach ($images as $image) {
            if(isset($image->parent_id)) {
                $thumbnailImage = Asset::find()
                    ->where([
                        'parent_id' => $image->parent_id,
                        'thumbnail' => Asset::THUMBNAIL_SMALL,
                    ])->one();
                $thumbnailImages[] = isset($thumbnailImage->id) ? $thumbnailImage : $image;
            } else {
                $thumbnailImages[] = $image;
            }
            $contentImagesHtml .= '<div><a href="'. $album->getPhotoUrl($image->id) .'">'.
                '<img src="'.$image->getFileUrl().'" alt="slide">'.
                '</a></div>';
        }
        foreach ($thumbnailImages as $image) {
            $thumbnailImagesHtml .= '<a class="pager-item" data-slide-index="'.$count.'" href="javascript:void(0)"><img src="'.$image->getFileUrl().'" alt="slide"></a>';
            $count++;
        }

        return Json::encode(compact('contentImagesHtml','thumbnailImagesHtml'));
    }

    /**
     * Album page with slider
     * @param int $album_id Album id
     * @param string $slug Album slug
     * @return mixed Content
     */
    public function actionPhoto($album_id, $slug, $photo_id) 
    {
        $album = Album::find()
            ->where([
                'id' => $album_id,
                'is_public' => 1,
            ])->one();

        if (!isset($album)){
            throw new NotFoundHttpException('Страница не найдена.');
        }

        $photo = Asset::find()
            ->where([
                'id' => $photo_id,
                'thumbnail' => Asset::THUMBNAIL_CONTENT,
            ])->one();
        if (!isset($photo)){
            $photo = Asset::find()
            ->where([
                'id' => $photo_id,
            ])->one();
        }

        if (!isset($photo)){
            throw new NotFoundHttpException('Страница не найдена.');
        }

        return $this->render('@frontend/views/site/index', [
            'templateType' => 'col2',
            'title' => '"Dynamomania.com" | Фотоальбом: '.$album->title,
            'columnFirst' => [
                'content' => [
                    'view' => '@frontend/views/site/photo_single',
                    'data' => compact('album', 'photo'),
                ],
                'comments' => Comment::getCommentsBlock($photo->id, Comment::COMMENTABLE_PHOTO),
            ],
            'columnSecond' => [ 
                'short_news' => SiteBlock::getShortNews(20),
            ],
        ]);
    }

    /**
     * Unsubscribe
     * @param $key string
     * @return mixed 
     */
    public function actionUnsubscribe($key)
    {
        $subscribings = Subscribing::find()->all();
        foreach ($subscribings as $subscribing) {
            if(md5($subscribing->id.$subscribing->email) === $key) {
                Yii::$app->session->setFlash("success-unsubscribe", "Ваш email ".$subscribing->email." успешно отписан от рассылки новостей.");
                $subscribing->delete();
            }
        }
        return $this->redirect(\yii\helpers\Url::to('/'));
        
    }

    /**
     * Rss output of last news
     * @return mixed 
     */
    public function actionNewsRss()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/rss+xml; charset=utf-8');

        $posts = Post::find()
            ->where(['is_public' => 1])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(50)
            ->all();
        $items = [];
        foreach ($posts as $post) {
            $content = $post->getShortContent(500, 700);
            $item = [
                'title' => htmlspecialchars($post->title),
                'link' => $post->url,
                'description' => htmlspecialchars($content),
                'pubDate' => date('r', strtotime($post->created_at)),
            ];
            $items[] = (object) $item;
        }
        $title = 'Динамомания: Новости';
        $description = 'Лента последних новостей';
        return $this->renderPartial('@frontend/views/site/rss', compact(
                'title',
                'description',
                'items'
            )
        );
    }

    /**
     * Rss output of last match events
     * @return mixed 
     */
    public function actionEventsRss()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/rss+xml; charset=utf-8');

        $events = MatchEvent::find()
            ->where(['not', ['is_hidden' => 1]])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(50)
            ->all();
        $items = [];
        foreach ($events as $event) {
            $content = strip_tags($event->notes,"<a><p><br>");
            $title = $event->match->name;
            if(isset($event->matchEventType)) {
                $title .= ' - '.$event->matchEventType->name;
            }
            $item = [
                'title' => htmlspecialchars($title),
                'link' => \yii\helpers\Url::to('/match/'.$event->match_id),
                'description' => htmlspecialchars($content),
                'pubDate' => date('r', strtotime($event->created_at)),
            ];
            $items[] = (object) $item;
        }
        $title = 'Динамомания: Трансляция';
        $description = 'Лента последних событий';
        return $this->renderPartial('@frontend/views/site/rss', compact(
                'title',
                'description',
                'items'
            )
        );
    }

    /**
     * Rss output of last news
     * @return mixed 
     */
    public function actionSocialRss($kind)
    {
        $kind = 'is_'.$kind.'_rss';
        $post = new Post();
        if(!$post->hasAttribute($kind)) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/rss+xml; charset=utf-8');

        $posts = Post::find()
            ->where([
                'is_public' => 1,
                $kind => 1,
            ])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(50)
            ->all();
        $items = [];
        foreach ($posts as $post) {
            $content = $post->getShortContent(500, 700);
            $item = [
                'title' => htmlspecialchars($post->title),
                'link' => $post->url,
                'description' => htmlspecialchars($content),
                'pubDate' => date('r', strtotime($post->created_at)),
            ];
            $items[] = (object) $item;
        }
        $title = 'Динамомания: Новости';
        $description = 'Лента последних новостей';
        return $this->renderPartial('@frontend/views/site/rss', compact(
                'title',
                'description',
                'items'
            )
        );
    }

    /**
     * Comparing a weight of blocks in columns
     * @param array $a
     * @param array $b
     * @return int Result of comparing
     */
    private static function cmp($a, $b)
    {
        if(!isset($a['weight'])) $a['weight'] = 0;
        if(!isset($b['weight'])) $b['weight'] = 0;
        if ($a['weight'] == $b['weight']) {
            return 0;
        }
        return ($a['weight'] < $b['weight']) ? -1 : 1;
    }
}
