<?php

Class embyapi
{

    private $embyIP = ""; //IP address of Emby server
    private $embyPort = ""; //Port number Emby is using
    private $embyApiKey = ""; //API key from Emby
    private $embyUser = ""; //User with admin rights that has been added in Emby (required to obtain Token
    private $embyPwd = ""; //User's pwd
    private $headers;
    private $embyDroidLinkToken;
    private $policyDto = array(
        "IsAdministrator" => false,
        "IsHidden" => true,
        "IsHiddenRemotely" => true,
        "IsDisabled" => false,
        "BlockedTags" => [],
        "IsTagBlockingModeInclusive" => false,
        "EnableUserPreferenceAccess" => false,
        "AccessSchedules" => [],
        "BlockUnratedItems" => [],
        "EnableRemoteControlOfOtherUsers" => false,
        "EnableSharedDeviceControl" => false,
        "EnableRemoteAccess" => false,
        "EnableLiveTvManagement" => false,
        "EnableLiveTvAccess" => false,
        "EnableMediaPlayback" => true,
        "EnableAudioPlaybackTranscoding" => false,
        "EnableVideoPlaybackTranscoding" => false,
        "EnablePlaybackRemuxing" => false,
        "EnableContentDeletion" => false,
        "EnableContentDeletionFromFolders" => [],
        "EnableContentDownloading" => false,
        "EnableSubtitleDownloading" => true,
        "EnableSubtitleManagement" => false,
        "EnableSyncTranscoding" => false,
        "EnableMediaConversion" => false,
        "EnabledDevices" => [],
        "EnableAllDevices" => true,
        "EnabledChannels" => [],
        "EnableAllChannels" => true,
        "EnabledFolders" => [],
        "EnableAllFolders" => true,
        "InvalidLoginAttemptCount" => 0,
        "EnablePublicSharing" => false,
        "RemoteClientBitrateLimit" => 0,
        "AuthenticationProviderId" => "Emby.Server.Implementations.Library.DefaultAuthenticationProvider",
        "ExcludedSubFolders" => [],
        "DisablePremiumFeatures" => false,
        "SimultaneousStreamLimit" => 1,
    );
    private $configDto = array(
        "PlayDefaultAudioTrack" => true,
        "DisplayMissingEpisodes" => false,
        "GroupedFolders" => [],
        "SubtitleMode" => "Default",
        "DisplayCollectionsView" => false,
        "EnableLocalPassword" => true,
        "OrderedViews" => [],
        "LatestItemsExcludes" => [],
        "MyMediaExcludes" => [],
        "HidePlayedInLatest" => true,
        "RememberAudioSelections" => true,
        "RememberSubtitleSelections" => true,
        "EnableNextEpisodeAutoPlay" => true,
    );

    public function __construct()
    {
        if (!isset($this->embyDroidLinkToken)) {
            $this->embyDroidLinkToken = $this->getDroidLinkToken();
        }
        $headers = [];
        $headers[] = "accept: application/json";
        $headers[] = "Content-Type: application/json";
        $headers[] = "X-Emby-Authorization: Emby UserId=\"(guid)\", Client=\"DroidLink\", Device=\"DroidLink API\", DeviceId=\"API\", Version=\"1.0\", Token=\"$this->embyDroidLinkToken\"";
        $this->headers = $headers;
    }

    private function curlExecute($url, $postdata = null)
    {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_HEADER => 0,
        ));
        if (isset($postdata)) {
            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postdata,
            ));
        }
        if (isset($customCurlRequest)){
            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_CUSTOMREQUEST => $customCurlRequest,
            ));
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    private function getDroidLinkToken()
    {
        $headers = [];
        $headers[] = "accept: application/json";
        $headers[] = "Content-Type: application/json";
        $headers[] = "X-Emby-Authorization: Emby UserId=\"(guid)\", Client=\"DroidLink\", Device=\"DroidLink API\", DeviceId=\"API\", Version=\"1.0\", Token=\"\"";
        $this->headers = $headers;
        $droidLinkUser = $this->embyUser;
        $droidLinkPwd = $this->embyPwd;
        $url = "http://$this->embyIP:$this->embyPort/emby/Users/AuthenticateByName";
        $postdata = array(
            "Username" => $droidLinkUser,
            "Password" => sha1($droidLinkPwd),
            "pw" => $droidLinkPwd,
        );
        $response = $this->curlExecute($url, json_encode($postdata));
        $response = get_object_vars(json_decode($response));
        $droidLinkToken = $response['AccessToken'];
        return $droidLinkToken;
    }

    public function createUser($username)
    {
        #Add user
        $url = "http://$this->embyIP:$this->embyPort/emby/Users/New?api_key=$this->embyApiKey";
        $postdata = array(
            "Name" => $username
        );
        $response = $this->curlExecute($url, json_encode($postdata));
        //TODO A user with the name 'blah' already exists. Response needs checking
        $response = get_object_vars(json_decode($response));
        return $response['Id'];
    }

    public function setPassword($userId, $password)
    {
        #Add password to user
        $url = "http://$this->embyIP:$this->embyPort/emby/Users/$userId/Password?api_key=$this->embyApiKey";
        $postdata = array(
            "Id" => $userId,
            "CurrentPw" => "",
            "NewPw" => $password,
            "ResetPassword" => false,
        );
        return $this->curlExecute($url, json_encode($postdata));
    }

    public function getUserDtoObject($userId)
    {
        $url = "http://$this->embyIP:$this->embyPort/emby/Users/$userId?api_key=$this->embyApiKey";
        /*$postdata = array(
            "Id" => $userId,
        );*/
        return $this->curlExecute($url);
    }

    public function updateEmbyPolicy($userId, $policyDto = null)
    {
        $url = "http://$this->embyIP:$this->embyPort/emby/Users/$userId/Policy?api_key=$this->embyApiKey";
        if (!isset($policyDto)) {
            $policyDto = json_encode($this->policyDto);
        }
        return $this->curlExecute($url, $policyDto);
    }

    public function updateConfiguration($userId, $configDto = null)
    {
        $url = "http://$this->embyIP:$this->embyPort/emby/Users/$userId/Configuration?api_key=$this->embyApiKey";
        if (!isset($configDto)) {
            $configDto = json_encode($this->configDto);
        }
        return $this->curlExecute($url, $configDto);
    }

    public function deleteUser($userid)
    {
        $url = "http://$this->embyIP:$this->embyPort/emby/Users/$userid?api_key=$this->embyApiKey";
        $customCurlRequest = "DELETE";
        return $this->curlExecute($url, null, $customCurlRequest);
    }

    public function getUserList()
    {
        $url = "http://$this->embyIP:$this->embyPort/emby/Users?api_key=$this->embyApiKey";
        return $this->curlExecute($url);
    }

    public function getUserIdFromName($username)
    {
        $userlist = $this->getUserList();
        $userlist = json_decode($userlist);
        foreach ($userlist as $user) {
            $user = get_object_vars($user);
            if ($user['Name'] === $username) {
                $userId = $user['Id'];
                break;
            }
        }
        if (isset($userId)) {
            return $userId;
        } else {
            return "User Not Found!";
        }
    }
}