<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleDataController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\TutorialController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//Askart Routes


Route::get('/disease-data', [ArticleController::class, 'getData']);

Route::get('/get-all-disease/', [AdminController::class, 'getDisease']);
Route::get('/tutorials', [TutorialController::class, 'index']);
Route::get('/tutorials/{id}', [TutorialController::class, 'show']);

Route::post('/tutorials', [TutorialController::class, 'store']);

Route::put('/tutorials/{id}', [TutorialController::class, 'update']);
Route::delete('/tutorials/{id}', [TutorialController::class, 'destroy']);

Route::get('/claims', [ClaimController::class, 'index']);             // List all claims

Route::get('/claims/{id}', [ClaimController::class, 'show']);         // Get single claim

Route::middleware(['cors'])->group(function () {

    //Public Data Explorer
    Route::post('/get-public-data-explorer/{filter}', [AdminController::class, 'publicDataExplorer']);

    //Keywords
    Route::post('/add-update-keyword/{keyword?}', [ContactController::class, 'addUpdateKeyword']);
    Route::get('/get-keywords', [ContactController::class, 'getKeywords']);
    Route::post('/delete-keyword/{id}', [ContactController::class, 'deleteKeyword']);

    /* Contact Form */
    Route::post('/submit-contact-form', [ContactController::class, 'contactSubmit']);
    /* Contact End */

    Route::post('/add-contributor', [AuthController::class, 'addContributor']);

    Route::post('/final-article-list2', [ArticleController::class, 'listArticles2']);

    Route::get('/get-bot-filters', [AdminController::class, 'ltFilters']);
    Route::post('/add-feedback', [AdminController::class, 'FeedbackStore']);
    Route::post('/delete-feedback/{id}', [AdminController::class, 'Feedbackdelete']);
    Route::get('/get-feedbacks', [AdminController::class, 'getFeedbacks']);
    Route::post('/update-feedback-status/{id}', [AdminController::class, 'updateFeedbackStatus']);
    Route::get('/get-biomarker-category/{id}', [AdminController::class, 'bioCategoriesById']);
    Route::post('/edit-biomarker-category/{id}', [AdminController::class, 'updateBioCategory']);
    Route::post('/add-biomarker-category', [AdminController::class, 'addBioCategory']);
    Route::post('/delete-biomarker-category/{id}', [AdminController::class, 'deleteBioCategory']);
    Route::post('/bot-scrapper-80', [ArticleController::class, 'getBotResponse3']);
    Route::post('/bot-scrapper-801', [ArticleController::class, 'getBotResponse2']);
    Route::post('/pdf-bot-scrapper', [ArticleController::class, 'pdfbot']);

    // Route::post('/upload-article-pdf', [AdminController::class, 'uploadArticle']);
    Route::post('/check-article', [AdminController::class, 'checkArticle']);
    Route::get('/fixarticles', [AdminController::class, 'fixArticle']);
    Route::get('/mh-to-mhi', [AdminController::class, 'mhtoMhi']);
    Route::get('/update-author', [AdminController::class, 'authorsUpdate']);
    Route::post('/login', [AuthController::class, 'login']);

    // Token-based password reset
    Route::post('/auth/forgot-password', [AuthController::class, 'resendPasswordOtp']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    // OTP-based password reset
    Route::post('/auth/password/otp/request', [AuthController::class, 'requestPasswordOtp']);
    Route::post('/auth/password/otp/resend', [AuthController::class, 'resendPasswordOtp']);
    Route::post('/auth/password/otp/verify', [AuthController::class, 'verifyPasswordOtp']);
    Route::post('/auth/password/otp/reset', [AuthController::class, 'resetPasswordWithOtp']);

    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::get('/get-article', [ArticleController::class, 'getArticles'])->name('get.article');

    Route::post('/filter-article', [ArticleController::class, 'filterArticles'])->name('filter.article');

    Route::post('/search-keywords', [ArticleController::class, 'keywordsArticles'])->name('keywords.article');
    Route::post('/search-year', [ArticleController::class, 'filterByYear'])->name('year.article');

    Route::get('/get-article/{id}', [ArticleController::class, 'getArticleByMhid'])->name('get.getArticleByMhid');
    // Route::get('/get-article/{id}', [ArticleController::class, 'getArticleById'])->name('get.articleById');
    Route::post('/search-by-url', [ArticleController::class, 'fetchArticleDetails'])->name('get.by.url');

    //Admin to Public Routes
    Route::get('/manage-main-sub-categories', [ArticleController::class, 'managedMakers']);
    Route::get('/get-auth-children', [AdminController::class, 'getAuthChildren']);
    Route::get('/get-countries', [AdminController::class, 'getCountries']);
    Route::get('/get-species', [ArticleController::class, 'allSpecies']);
    Route::get('/get-study-type', [ArticleController::class, 'getStudyType']);
    Route::get('/get-research-topic', [ArticleController::class, 'getResearchTopic']);
    Route::get('/get-systems', [ArticleController::class, 'getSystems']);
    Route::get('/get-organs', [ArticleController::class, 'getOrgans']);
    Route::get('/get-methods', [ArticleController::class, 'getMethods']);

    Route::get('/home-page', [ArticleController::class, 'HomePage']);
    Route::get('/get-title', [ArticleController::class, 'getTitle']);
    Route::post('/final-article-list', [ArticleController::class, 'listArticles']);
    Route::post('/final-article-list-main', [ArticleController::class, 'listArticlesMain']);
    Route::get('/get-fiters', [ArticleController::class, 'getFilters']);

    Route::get('/update-all-mhid', [ArticleController::class, 'updateAllArticlesWithMhid']);
    // Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    //     return $request->user();
    // });
    Route::post('/upload-images', [AdminController::class, 'uploadImages']);
    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('articles/data')->group(function () {
            Route::post('/add', [ArticleDataController::class, 'addData']);
            Route::delete('/remove', [ArticleDataController::class, 'removeData']);
            Route::get('/get', [ArticleDataController::class, 'getData']);
        });

        Route::post('/claims', [ClaimController::class, 'store']);            // Create new claim
        Route::put('/claims/status/{id}', [ClaimController::class, 'updateStatus']);
        Route::put('/claims/{id}', [ClaimController::class, 'update']);
        Route::delete('/claims/{id}', [ClaimController::class, 'destroy']);   // Delete claim

        Route::post('/articles/{id}/toggle-trending', [ArticleController::class, 'toggleTrending']);
        Route::post('/compare-articles-by-filter', [ArticleController::class, 'compareArticlesByFilter']);

        Route::post('/final-article-submit', [ArticleController::class, 'articleSubmit']);
        Route::post('/bulk-article-submit', [ArticleController::class, 'bulkArticleSubmit']); // NEW

        Route::post('/process-and-submit', [ArticleController::class, 'processAndSubmitPdf']);

        Route::post('/upload-article-pdf', [AdminController::class, 'uploadArticle']);
        Route::get('/check-auth', [AuthController::class, 'checkAuth'])->name('check.auth');
        Route::get('/saved-search', [SavedSearchController::class, 'getSavedSearches']);
        Route::post('/save-search', [SavedSearchController::class, 'saveSearch'])->name('save.search');
        Route::delete('/saved-search/{id}', [SavedSearchController::class, 'destroy']);
        Route::post('/final-article-list-admin', [ArticleController::class, 'listArticles']);
        Route::post('/researcher-articles', [ArticleController::class, 'ResearcherArticle']);
        Route::post('/add-specie', [ArticleController::class, 'AddSpecie']);
        Route::post('/edit-specie/{spid}', [ArticleController::class, 'EditSpecie']);
        Route::get('/view-specie/{specie}', [ArticleController::class, 'ViewSpecie']);

        Route::post('/delete-species/{id}', [ArticleController::class, 'deleteSpecie']);

        Route::post('/add-disease', [ArticleController::class, 'AddDisease']);
        Route::post('/edit-disease/{id}', [ArticleController::class, 'EditDisease']);
        Route::get('/view-disease/{disease}', [ArticleController::class, 'ViewDisease']);
        Route::post('/delete-disease/{id}', [ArticleController::class, 'DeleteDisease']);

        Route::get('/get-disease', [ArticleController::class, 'allDiseases']);

        Route::post('/add-biomaker', [ArticleController::class, 'addBioMaker']);
        Route::post('/add-biomaker-front', [ArticleController::class, 'updateMarkerFromFront']);
        Route::post('/edit-biomaker/{id}', [ArticleController::class, 'editBioMaker']);
        Route::get('/get-biomakers', [ArticleController::class, 'getBiomakers']);

        Route::post('/add-study-type', [ArticleController::class, 'addStudyType']);

        Route::post('/edit-study-type/{id}', [ArticleController::class, 'editStudyType']);
        Route::post('/delete-study-type/{id}', [ArticleController::class, 'deleteStudyType']);

        Route::post('/add-research-topic', [ArticleController::class, 'addResearchTopic']);
        Route::post('/edit-research-topic/{id}', [ArticleController::class, 'editResearchTopic']);
        Route::post('/delete-research-topic/{id}', [ArticleController::class, 'deleteResearchTopic']);

        Route::post('/add-organs', [ArticleController::class, 'addOrgans']);
        Route::post('/edit-organs/{id}', [ArticleController::class, 'editOrgans']);

        Route::post('/delete-organs/{id}', [ArticleController::class, 'deleteOrgan']);

        Route::post('/add-systems', [ArticleController::class, 'addSystem']);
        Route::post('/edit-systems/{id}', [ArticleController::class, 'editSystem']);

        Route::post('/delete-systems/{id}', [ArticleController::class, 'deletesystems']);

        Route::post('/add-methods', [ArticleController::class, 'addMethods']);
        Route::post('/edit-methods/{id}', [ArticleController::class, 'editMethods']);

        Route::post('/delete-methods/{id}', [ArticleController::class, 'deleteMethods']);

        Route::post('/submit-article-form', [ArticleController::class, 'newArticle']);
        Route::get('/get-new-articles', [ArticleController::class, 'getPArticles']);
        Route::post('/file-upload', [ArticleController::class, 'upload']);
        Route::post('/check-pmid/{pmid}', [ArticleController::class, 'getPMID']);

        Route::post('/add-biomarker', [ArticleController::class, 'addBioMarker']);
        Route::post('/edit-biomarker/{id}', [ArticleController::class, 'editBioMarker']);
        Route::get('/view-marker/{id}', [ArticleController::class, 'viewMarker']);
        Route::get('/list-sub-cat', [ArticleController::class, 'BioSubWithCatList']);
        Route::post('/reject-approve-makers', [ArticleController::class, 'RejectApproveMakers']);

        //Admin Routes
        Route::get('/get-all-users', [AdminController::class, 'userList']);
        Route::get('/get-contributor', [AdminController::class, 'contributorList']);
        Route::post('/approve-reject-contributor', [AdminController::class, 'approveRejectContributor']);
        Route::get('/user/{user}', [AdminController::class, 'userByID']);
        Route::post('/add-update-user/{id?}', [AdminController::class, 'userAddUpdate']);
        Route::post('/delete-user/{id}', [AdminController::class, 'deleteUser']);
        Route::get('/admin-dashboard', [AdminController::class, 'adminDashboard']);
        Route::post('/update-time', [AdminController::class, 'updateTime']);
        Route::post('/delete-article/{id}', [AdminController::class, 'deleteArticle']);
        Route::post('/article-delete/{id}', [AdminController::class, 'deleteArticle']);
        Route::post('/update-status/{id}', [AdminController::class, 'updateStatus']);
        Route::get('/get-article-authors', [AdminController::class, 'getArticleAuthors']);
        Route::post('/verify-author', [AdminController::class, 'verifyAuthor']);
        Route::get('/get-authors', [AdminController::class, 'getAuthors']);
        Route::post('/add-author', [AdminController::class, 'addAuthor']);
        Route::post('/authors/{id}/featured/{isFeatured?}', [AdminController::class, 'featuredAuthor']);

        Route::post('/send-mail', [ContactController::class, 'sendMail']);


        Route::post('/add-author-childr', [AdminController::class, 'addAuthorChild']);
        Route::post('/edit-author/{author}', [AdminController::class, 'editAuthor']);

        Route::post('/add-country', [AdminController::class, 'addCountries']);
        Route::post('/edit-country/{country}', [AdminController::class, 'editCountry']);
        Route::post('/delete-country/{country}', [AdminController::class, 'deleteCountry']);

        Route::post('/add-role', [AdminController::class, 'addRole']);
        Route::get('/get-roles', [AdminController::class, 'getRoles']);
        Route::get('/get-role/{role}', [AdminController::class, 'viewRole']);
        Route::post('/edit-role/{role}', [AdminController::class, 'editRole']);
        Route::post('/delete-role/{role}', [AdminController::class, 'deletetRole']);
        Route::post('/assign-reviewer', [AdminController::class, 'assignReviewer']);
        Route::post('/get-assignment-articles', [AdminController::class, 'getListArticlesForAssignment']);

        Route::get('/get-submit-contact-form', [ContactController::class, 'getContacts']);
        Route::post('/delete-form-contact/{id}', [ContactController::class, 'deleteContactForm']);

        //Researcher

        Route::post('/get-researcher-articles', [AdminController::class, 'getAssignedArticles']);

    });
});
