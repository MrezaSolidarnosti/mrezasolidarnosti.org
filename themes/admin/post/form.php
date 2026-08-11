<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?=$this->e($pageTitle)?></title>
    <link rel="stylesheet" href="<?=ADMIN_ASSET_URL . '/css/style.css?v=0.0.8'?>">
    <link rel="stylesheet" href="<?=ADMIN_ASSET_URL . '/vendor/skeletorJS/css/style.css?v=0.0.1'?>">
    <link rel="shortcut icon" href="<?= ADMIN_ASSET_URL ?>/images/favicon.ico"/>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= ADMIN_ASSET_URL ?>/images/apple-touch-icon.png"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100;0,9..40,200;0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;0,9..40,900;0,9..40,1000;1,9..40,100;1,9..40,200;1,9..40,300;1,9..40,400;1,9..40,500;1,9..40,600;1,9..40,700;1,9..40,800;1,9..40,900;1,9..40,1000&display=swap" rel="stylesheet">
</head>
<body>
<?=$this->formToken()?>
<script>
    const initialContent = <?=json_encode($data['initialContent'])?>;
    const action = '<?=$data['formAction']?>';
</script>
<div id="contentEditor">
    <div id="topBar">
        <div id="topBarLeft">
            <div id="blockInserterButton" title="Add block" data-title-close="Close">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"/></svg>
            </div>
            <div id="overviewButton" title="Overview">
                <svg xmlns="http://www.w3.org/2000/svg" height="28px" viewBox="0 -960 960 960" width="28px" fill="#e3e3e3"><path d="M120-280v-66.67h553.33V-280H120Zm0-166.67v-66.66h553.33v66.66H120Zm0-166.66V-680h553.33v66.67H120ZM806.33-280q-14 0-23.5-9.58-9.5-9.59-9.5-23.75 0-13.67 9.59-23.5 9.58-9.84 23.75-9.84 13.66 0 23.5 9.84Q840-327 840-313q0 14-9.83 23.5-9.84 9.5-23.84 9.5Zm0-166.67q-14 0-23.5-9.58t-9.5-23.75q0-13.67 9.59-23.5 9.58-9.83 23.75-9.83 13.66 0 23.5 9.83 9.83 9.83 9.83 23.83 0 14-9.83 23.5-9.84 9.5-23.84 9.5Zm0-166.66q-14 0-23.5-9.59-9.5-9.58-9.5-23.75 0-13.66 9.59-23.5 9.58-9.83 23.75-9.83 13.66 0 23.5 9.83 9.83 9.84 9.83 23.84t-9.83 23.5q-9.84 9.5-23.84 9.5Z"/></svg>
            </div>
            <div id="undoRedoContainer">
                <div id="undoButton" title="Undo">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M280-200v-80h284q63 0 109.5-40T720-420q0-60-46.5-100T564-560H312l104 104-56 56-200-200 200-200 56 56-104 104h252q97 0 166.5 63T800-420q0 94-69.5 157T564-200H280Z"/></svg>
                </div>
                <div id="redoButton" title="Redo">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M396-200q-97 0-166.5-63T160-420q0-94 69.5-157T396-640h252L544-744l56-56 200 200-200 200-56-56 104-104H396q-63 0-109.5 40T240-420q0 60 46.5 100T396-280h284v80H396Z"/></svg>
                </div>
            </div>
        </div>
        <div id="topBarCenter">

        </div>
        <div id="topBarRight">
            <div id="seo" title="SEO" data-module="seo">
                <span>SEO</span>
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M400-320q100 0 170-70t70-170q0-100-70-170t-170-70q-100 0-170 70t-70 170q0 100 70 170t170 70Zm-40-120v-280h80v280h-80Zm-140 0v-200h80v200h-80Zm280 0v-160h80v160h-80ZM824-80 597-307q-41 32-91 49.5T400-240q-134 0-227-93T80-560q0-134 93-227t227-93q134 0 227 93t93 227q0 56-17.5 106T653-363l227 227-56 56Z"/></svg>
            </div>
            <div id="save" title="Save">
                <span>Save</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M64 80c-8.8 0-16 7.2-16 16l0 320c0 8.8 7.2 16 16 16l320 0c8.8 0 16-7.2 16-16l0-242.7c0-4.2-1.7-8.3-4.7-11.3L320 86.6 320 176c0 17.7-14.3 32-32 32l-160 0c-17.7 0-32-14.3-32-32l0-96-32 0zm80 0l0 80 128 0 0-80-128 0zM0 96C0 60.7 28.7 32 64 32l242.7 0c17 0 33.3 6.7 45.3 18.7L429.3 128c12 12 18.7 28.3 18.7 45.3L448 416c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96zM160 320a64 64 0 1 1 128 0 64 64 0 1 1 -128 0z"/></svg>
            </div>
            <div id="userSettings" title="Editor settings">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m370-80-16-128q-13-5-24.5-12T307-235l-119 50L78-375l103-78q-1-7-1-13.5v-27q0-6.5 1-13.5L78-585l110-190 119 50q11-8 23-15t24-12l16-128h220l16 128q13 5 24.5 12t22.5 15l119-50 110 190-103 78q1 7 1 13.5v27q0 6.5-2 13.5l103 78-110 190-118-50q-11 8-23 15t-24 12L590-80H370Zm112-260q58 0 99-41t41-99q0-58-41-99t-99-41q-59 0-99.5 41T342-480q0 58 40.5 99t99.5 41Z"/></svg>
            </div>
            <div id="toggleSidebar" title="Toggle Sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm440-80h120v-560H640v560Zm-80 0v-560H200v560h360Zm80 0h120-120Z"/></svg>
            </div>
        </div>
    </div>
    <div id="messagesContainer">

    </div>
    <div id="contentContainer">
        <h1 id="title" contenteditable="true" data-placeholder="Add title" data-module="title" spellcheck="false"></h1>
        <div id="content">

        </div>
    </div>
    <div id="sidebar" class="active">
        <div id="sidebarNavigation">
            <span data-target="sidebarEntityContent">Content</span>
            <span data-target="sidebarBlockContent">Block</span>
            <span id="closeSidebar" title="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"/></svg>
                </span>
        </div>
        <div id="sidebarEntityContent" class="sidebarContent">
            <div id="featuredImage" class="mediaLibraryInitiator" data-insertable="true" data-images="true" data-multiple="false" data-module="featuredImage">
                <input type="hidden" name="featuredImage" id="featuredImageId">
                <span>Featured Image</span>
            </div>
            <div id="statusContainer" data-module="status">
                <input type="hidden" id="status" value="2">
                <div id="statusViewContainer">
                    <span>Status</span>
                    <div id="statusView">
                        Draft
                    </div>
                </div>
                <input type="datetime-local" class="input" id="scheduleDt">
            </div>
            <div id="statuses" data-module="status">
                <div id="statusesTopBar">
                    <span>Status</span>
                    <div id="closeStatuses" title="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"/></svg>
                    </div>
                </div>
                <label>
                    <input checked type="radio" class="input" name="status" value="2">
                    <span class="statusNameContainer">
                            <span class="statusName">Draft</span>
                            <span class="description">Not ready to publish.</span>
                        </span>
                </label>
                <label>
                    <input type="radio" class="input" name="status" value="3">
                    <span class="statusNameContainer">
                            <span class="statusName">Pending</span>
                            <span class="description">Waiting for review before publishing.</span>
                        </span>
                </label>
                <label>
                    <input type="radio" class="input" name="status" value="4" data-schedule="true">
                    <span class="statusNameContainer">
                            <span class="statusName">Scheduled</span>
                            <span class="description">Publish automatically on a chosen date.</span>
                        </span>
                </label>
                <label>
                    <input type="radio" class="input" name="status" value="1">
                    <span class="statusNameContainer">
                            <span class="statusName">Published</span>
                            <span class="description">Visible to everyone.</span>
                        </span>
                </label>
            </div>
            <div id="slugContainer" data-module="slug">
                <label>Slug</label>
                <input type="text" id="slug" placeholder="your-slug">
            </div>
        </div>
        <div id="sidebarBlockContent" class="sidebarContent">
        </div>
    </div>
    <div id="bottomBar">
        <div id="shortcutsButton" title="Shortcuts">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M400-280h160v-80H400v80Zm0-160h280v-80H400v80ZM280-600h400v-80H280v80Zm200 120ZM265-80q-79 0-134.5-55.5T75-270q0-57 29.5-102t77.5-68H80v-80h240v240h-80v-97q-37 8-61 38t-24 69q0 46 32.5 78t77.5 32v80Zm135-40v-80h360v-560H200v160h-80v-160q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H400Z"/></svg>
        </div>
    </div>
    <div id="blockSideToggle">
        <div title="Drag" id="blockSideToggleDragHandle" class="blockSideAction">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px"
                 fill="#e3e3e3">
                <path d="M360-160q-33 0-56.5-23.5T280-240q0-33 23.5-56.5T360-320q33 0 56.5 23.5T440-240q0 33-23.5 56.5T360-160Zm240 0q-33 0-56.5-23.5T520-240q0-33 23.5-56.5T600-320q33 0 56.5 23.5T680-240q0 33-23.5 56.5T600-160ZM360-400q-33 0-56.5-23.5T280-480q0-33 23.5-56.5T360-560q33 0 56.5 23.5T440-480q0 33-23.5 56.5T360-400Zm240 0q-33 0-56.5-23.5T520-480q0-33 23.5-56.5T600-560q33 0 56.5 23.5T680-480q0 33-23.5 56.5T600-400ZM360-640q-33 0-56.5-23.5T280-720q0-33 23.5-56.5T360-800q33 0 56.5 23.5T440-720q0 33-23.5 56.5T360-640Zm240 0q-33 0-56.5-23.5T520-720q0-33 23.5-56.5T600-800q33 0 56.5 23.5T680-720q0 33-23.5 56.5T600-640Z"/>
            </svg>
        </div>
        <div title="Move up" id="blockSideToggleMoveUp" class="blockSideAction">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-528 296-344l-56-56 240-240 240 240-56 56-184-184Z"/></svg>
        </div>
        <div title="Move down" id="blockSideToggleMoveDown" class="blockSideAction">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-344 240-584l56-56 184 184 184-184 56 56-240 240Z"/></svg>
        </div>
        <div title="More" id="blockSideToggleMore" class="blockSideAction">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-160q-33 0-56.5-23.5T400-240q0-33 23.5-56.5T480-320q33 0 56.5 23.5T560-240q0 33-23.5 56.5T480-160Zm0-240q-33 0-56.5-23.5T400-480q0-33 23.5-56.5T480-560q33 0 56.5 23.5T560-480q0 33-23.5 56.5T480-400Zm0-240q-33 0-56.5-23.5T400-720q0-33 23.5-56.5T480-800q33 0 56.5 23.5T560-720q0 33-23.5 56.5T480-640Z"/></svg>
        </div>
        <div id="blockSideToggleMoreMenu">
                <span id="blockSideToggleAddBefore">
                    Insert Before
                </span>
            <span id="blockSideToggleAddAfter">
                    Insert After
                </span>
            <span id="blockSideToggleDuplicate">
                    Duplicate
                </span>
            <span id="blockSideToggleDelete">
                    Delete
                </span>
        </div>
    </div>
    <div id="revisionsModal" data-module="revisions">
        <div id="revisionsHeader">
            <h2>Revisions</h2>
            <p>Compare a revision with the current content, and restore it.</p>
            <div id="closeRevisions" title="Close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"></path></svg>
            </div>
        </div>
        <div class="revisionsModalBody">
            <div id="revisionsModalList" class="revisionsList"></div>
            <div class="revisionsDetail">
                <div class="revisionsDetailHeader">
                    <span id="revisionsSummary"></span>
                    <div id="revisionsRevert" class="btn">Revert to this revision</div>
                </div>
                <div id="revisionsFields"></div>
                <div id="revisionsDiff"></div>
            </div>
        </div>
    </div>
    <div id="seoModal" data-module="seo">
        <div id="seoHeader">
            <h2>SEO Settings</h2>
            <p>Optimize how your content appears in search engines.</p>
            <div id="closeSeo" title="Close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"></path></svg>
            </div>
        </div>

        <div id="seoTitleContainer" class="inputWithCounter">
            <div class="top">
                <label>Title</label>
                <div class="counter" data-red="0-20" data-orange="21-40" data-yellow="41-49" data-green="50-60">
                    <span class="currentCharacters">0</span>
                    <span>/</span>
                    <span class="maxCharacters">60</span>
                </div>
            </div>
            <input placeholder="Enter SEO title..." spellcheck="false" class="input" type="text" id="seoTitle">
        </div>
        <div id="seoDescriptionContainer" class="inputWithCounter">
            <div class="top">
                <label>Description</label>
                <div class="counter" data-red="0-80" data-orange="81-100" data-yellow="101-149" data-green="150-160">
                    <span class="currentCharacters">0</span>
                    <span>/</span>
                    <span class="maxCharacters">160</span>
                </div>
            </div>
            <textarea placeholder="Enter meta description..." spellcheck="false" class="input" id="seoDescription"></textarea>
        </div>
        <div id="seoImageContainer">
            <label>SEO Image</label>
            <div id="seoImage" class="mediaLibraryInitiator" data-insertable="true" data-images="true" data-multiple="false">
                <div id="seoImageDescription">
                    <img src="" alt="SEO image">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm40-80h480L570-480 450-320l-90-120-120 160Zm-40 80v-560 560Z"></path></svg>
                    <span class="title">Choose Image</span>
                    <span class="requirements">Recommended size: 1200 x 630px</span>
                </div>
            </div>
        </div>
    </div>
    <div id="userSettingsModal">
        <div id="userSettingsHeader">
            <h2>Editor settings</h2>
            <p>Adapt how the editor looks for you.</p>
            <div id="closeUserSettings" title="Close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"></path></svg>
            </div>
        </div>
        <div id="userSettingsList"></div>
    </div>
    <div id="shortcutsContainer">
        <div id="shortcutsHeader">
            <h2>Keyboard Shortcuts</h2>
            <div id="closeShortcuts" title="Close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"></path></svg>
            </div>
        </div>
    </div>
    <div id="overviewContainer">
    </div>
    <div id="blockInserter">
        <div id="blockInserterHeader">
            <span>Blocks</span>
            <div id="blockInserterClose" title="Close">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M256-200l-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>
            </div>
        </div>
        <div class="blockInserterSearchField">
            <span class="blockInserterSearchIcon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376C296.3 401.1 253.9 416 208 416 93.1 416 0 322.9 0 208S93.1 0 208 0 416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path></svg></span>
            <input type="text" id="blockInserterSearch" class="input" placeholder="Search">
        </div>
        <div id="blockInserterList"></div>
    </div>
</div>
<!-- Media Library start -->
<div id="mediaLibraryOverlay" class="hidden">
    <div id="mediaLibrary">
        <div id="mediaLibraryTopBar">
            <div id="uploadMedia" title="Upload">
                <input type="file" multiple id="uploadMediaInput">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                    <path d="M144 480C64.5 480 0 415.5 0 336c0-62.8 40.2-116.2 96.2-135.9c-.1-2.7-.2-5.4-.2-8.1c0-88.4 71.6-160 160-160c59.3 0 111 32.2 138.7 80.2C409.9 102 428.3 96 448 96c53 0 96 43 96 96c0 12.2-2.3 23.8-6.4 34.6C596 238.4 640 290.1 640 352c0 70.7-57.3 128-128 128H144zm79-217c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l39-39V392c0 13.3 10.7 24 24 24s24-10.7 24-24V257.9l39 39c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-80-80c-9.4-9.4-24.6-9.4-33.9 0l-80 80z"/>
                </svg>
                <span>File Upload</span>
            </div>
            <input aria-label="Search" type="text" placeholder="Search..." class="input" id="searchMediaLibraryInput">
            <select aria-label="File Type" class="input" id="mediaLibraryFileTypeSelect">
                <option value="0" disabled>Images</option>
                <option value="1" disabled>Documents</option>
            </select>
            <input aria-label="From" type="date" class="input" id="mediaLibraryDateFrom">
            <input aria-label="To" type="date" class="input" id="mediaLibraryDateTo">
            <div id="closeMediaLibrary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                    <path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/>
                </svg>
            </div>
            <div id="mediaLibraryProgressBarContainer"></div>
        </div>
        <div id="mediaLibraryContent">
            <div id="cellsWrapper">
                <div id="cells">

                </div>
            </div>
            <div id="mediaLibrarySidebar">
                <div id="mediaLibrarySidebarMessageContainer"></div>
            </div>
        </div>
        <div id="mediaLibraryBottomBar">
            <button class="btn primary hidden" id="insertMedia">
                Insert
            </button>
        </div>
    </div>
</div>
<div id="editorOverlay"></div>
<!-- Media Library end -->
<script src="<?=ADMIN_ASSET_URL .'/js/global.js?v=1.0.6'?>" type="module"></script>
<script src="<?=ADMIN_ASSET_URL .'/js/post/post.js'?>" type="module"></script>
</body>
</html>