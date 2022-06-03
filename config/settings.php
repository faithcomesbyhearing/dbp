<?php

return [
  /*
   * Is email activation required
   */
  'app_project_version' => env('APP_PROJECT_VERSION'),

  /*
   * Is email activation required
   */
  'activation' => env('ACTIVATION', false),

  /*
   * Is email activation required
   */
  'timePeriod' => env('ACTIVATION_LIMIT_TIME_PERIOD', 24),

  /*
   * Is email activation required
   */
  'maxAttempts' => env('ACTIVATION_LIMIT_MAX_ATTEMPTS', 3),

  /*
   * NULL Ip to enter to match database schema
   */
  'nullIpAddress' => env('NULL_IP_ADDRESS', '0.0.0.0'),

  /*
   * User restore encryption type
   */
  'restoreUserEncType' => 'AES-256-ECB',

  /*
   * User restore days past cutoff
   */
  'restoreUserCutoff' => env('USER_RESTORE_CUTOFF_DAYS', 31),

  /*
   * User list pagination size
   */
  'userListPaginationSize' => env('USER_LIST_PAGINATION_SIZE', 50),

  /*
   * v2 v4 sync chunk size
   */
  'v2V4SyncChunkSize' => env('V2_V4_SYNC_CHUNK_SIZE', 5000),

  /*
   * v2 v4 sync chunk size
   */
  'liveBibleisV4SyncChunkSize' => env('LIVE_BIBLEIS_V4_SYNC_CHUNK_SIZE', 500),
  
  /*
   * Default project id to assign to v2 to v4 users
   */
  'defaultProjectId' => env('USER_DEFAULT_PROJECT_ID', 52341),

  /*
   * Api Key access groups assigned for permissions
   */

  'apiKeyAccessGroups' => env('API_KEY_ACCESS_GROUPS', '121,123,125'),

  /*
   * User restore encryption key
   */
  'restoreKey' => env(
    'USER_RESTORE_ENCRYPTION_KEY',
    'sup3rS3cr3tR35t0r3K3y21!'
  ),

  /*
   * ReCaptcha Status
   */
  'reCaptchStatus' => env('ENABLE_RECAPTCHA', false),

  /*
   * ReCaptcha Site Key
   */
  'reCaptchSite' => env('RE_CAP_SITE', 'YOURGOOGLECAPTCHAsitekeyHERE'),

  /*
   * ReCaptcha Secret
   */
  'reCaptchSecret' => env('RE_CAP_SECRET', 'YOURGOOGLECAPTCHAsecretHERE'),

  /*
   * Google Maps API V3 Status
   */
  'googleMapsAPIStatus' => env('GOOGLEMAPS_API_STATUS', false),

  /*
   * Google Maps API Key
   */
  'googleMapsAPIKey' => env('GOOGLEMAPS_API_KEY', 'YOURGOOGLEMAPSkeyHERE'),

  /*
   * DropZone CDN
   */
  'dropZoneJsCDN' => env(
      'DROPZONE_JS_CDN',
      'https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.4.0/dropzone.js'
  ),
  
  /*
    * Bible sync path
    */
  'bibleSyncFilePath' => env('BIBLE_SYNC_FILE_PATH', ''),

  'apiLatestVersion' => env('API_LATEST_VERSION', '4'),

  /*
   * Arclight forbiddenn iso list
   */
  'forbiddenArclightIso' => env('FORBIDDEN_ARCLIGHT_ISO', 'hun'),

  /*
   * Version to start bibleis/gid deprecation
   */
  'deprecate_from_version' => [
      'bibleis' => env('BIBLEIS_DEPRECATE_FROM_VERSION', '3.3.10'),
      'gideons' => env('GIDEONS_DEPRECATE_FROM_VERSION', '2.1.3'),
  ],

  /*
   * Download access group list allowed
   */
  'download_access_group_list' => env('DOWNLOAD_ACCESS_GROUP_LIST', '12,181,183,185,191,193'),

  'backward_compatibility' => [
      'app_name' => [
        'bibleis' => env('BIBLEIS_COMPATIBILITY_APP_NAME', 'Bible.is'),
        'gideons' => env('GIDEONS_COMPATIBILITY_APP_NAME', 'Gideon Bible App'),
      ]
  ],

  'youtube_url' => env('YOUTUBE_URL', 'https://www.youtube.com/')
];
