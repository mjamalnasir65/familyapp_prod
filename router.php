<?php
// Early lowercase normalization: redirect any request URI containing uppercase letters
// Helps prevent Linux case mismatch 404s and enforces canonical lowercase path usage
if (isset($_SERVER['REQUEST_URI']) && preg_match('/[A-Z]/', $_SERVER['REQUEST_URI'])) {
    $lower = strtolower($_SERVER['REQUEST_URI']);
    if ($lower !== $_SERVER['REQUEST_URI']) {
        header('Location: ' . $lower, true, 301);
        exit;
    }
}
/**
 * Smart Router for Family Echo Application
 * Handles language selection, authentication, and intelligent user journey routing
 * 
 * Session Policy:
 * - Landing page (/) has NO session
 * - Session starts ONLY when language is selected OR login/register is clicked
 * - Logout destroys all sessions and redirects to landing page
 */

// Load configuration and core classes
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

class SmartRouter {
    private $lang;
    private $userId;
    private $requestUri;
    private $queryString;
    private $langFolders = ['EN', 'MY'];
    private $defaultLang = 'EN';
    private $db;
    private $basePath = '';
    private $sessionStarted = false;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->queryString = $_SERVER['QUERY_STRING'] ?? '';
        
        // DO NOT start session for landing page
        // Session will be started only when needed (language selection, login, register)
        if (!$this->isLandingPage()) {
            $this->startSessionIfNeeded();
            $this->initializeLanguage();
            $this->initializeUser();
        }
    }
    
    /**
     * Check if current request is for landing page
     */
    private function isLandingPage() {
        return $this->requestUri === '/' || 
               $this->requestUri === '/index.php' || 
               $this->requestUri === '/index.html';
    }
    
    /**
     * Start session only when needed (not for landing page)
     */
    private function startSessionIfNeeded() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->sessionStarted = true;
        }
    }
    
    /**
     * Initialize language from session, cookie, query param, or browser
     * Session is STARTED here if language is explicitly selected
     */
    private function initializeLanguage() {
        // Priority: URL param > Session > Cookie > Browser > Default
        if (isset($_GET['lang']) && in_array(strtoupper($_GET['lang']), $this->langFolders)) {
            // Language explicitly selected - ensure session is started
            if (!$this->sessionStarted) {
                $this->startSessionIfNeeded();
            }
            $this->lang = strtoupper($_GET['lang']);
            $_SESSION['lang'] = $this->lang;
            setcookie('lang', $this->lang, time() + (365 * 24 * 60 * 60), '/');
        } elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $this->langFolders)) {
            $this->lang = $_SESSION['lang'];
        } elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $this->langFolders)) {
            $this->lang = $_COOKIE['lang'];
            if ($this->sessionStarted) {
                $_SESSION['lang'] = $this->lang;
            }
        } else {
            // Detect from browser
            $browserLang = $this->detectBrowserLanguage();
            $this->lang = $browserLang ?: $this->defaultLang;
            if ($this->sessionStarted) {
                $_SESSION['lang'] = $this->lang;
                setcookie('lang', $this->lang, time() + (365 * 24 * 60 * 60), '/');
            }
        }
    }
    
    /**
     * Detect browser language preference
     */
    private function detectBrowserLanguage() {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($langs as $lang) {
                $langCode = strtoupper(substr(trim($lang), 0, 2));
                if ($langCode === 'MS' || $langCode === 'MY') return 'MY';
                if ($langCode === 'EN') return 'EN';
            }
        }
        return null;
    }
    
    /**
     * Initialize user session
     */
    private function initializeUser() {
        $this->userId = $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Main routing logic
     */
    public function route() {
        // Handle logout first (before any other routing)
        if ($this->isLogoutRequest()) {
            return $this->handleLogout();
        }
        
        // Handle root/landing page (NO SESSION)
        if ($this->isLandingPage()) {
            return $this->handleLandingPage();
        }
        
        // Handle API requests (pass through - let PHP files handle themselves)
        if (strpos($this->requestUri, '/api/') === 0) {
            return false;
        }
        
        // Handle old /auth/ requests - redirect to language-specific auth
        if (strpos($this->requestUri, '/auth/') === 0) {
            return $this->redirectToLangAuth();
        }
        
        // Handle language-specific pages
        if (preg_match('#^/pages/(EN|MY)/(.+)#', $this->requestUri, $matches)) {
            $requestedLang = $matches[1];
            $page = $matches[2];
            
            // Update session lang if different
            if ($requestedLang !== $this->lang) {
                $this->lang = $requestedLang;
                $_SESSION['lang'] = $requestedLang;
                setcookie('lang', $requestedLang, time() + (365 * 24 * 60 * 60), '/');
            }
            
            return $this->handleLangPage($page);
        }
        
        // Handle assets (pass through to web server)
        if (preg_match('#^/(assets|uploads)/#', $this->requestUri)) {
            return false;
        }
        
        // Handle manifest and static files
        if (preg_match('#\.(css|js|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot|ico|webmanifest)$#', $this->requestUri)) {
            return false;
        }
        
        // Handle friendly URLs (short paths without /pages/LANG/)
        if ($friendlyRoute = $this->handleFriendlyUrl()) {
            return $friendlyRoute;
        }
        
        // Default: redirect to appropriate landing
        return $this->handleDefault();
    }
    
    /**
     * Handle friendly URLs (short paths without /pages/LANG/ prefix)
     * Maps URLs like /dashboard, /login, /register to their full paths
     * Comprehensive mapping based on COMPLETE_PATHS_LIST.md
     */
    private function handleFriendlyUrl() {
        // Remove leading slash and any query string
        $path = trim($this->requestUri, '/');
        
        // Map of friendly URLs to actual page paths
        $friendlyMap = [
            // ============================================
            // AUTHENTICATION PAGES
            // ============================================
            'login' => 'auth/login.php',
            'register' => 'auth/register.php',
            'logout' => 'LOGOUT_ACTION', // Special marker - handled separately
            'tok_register' => 'auth/tok_register.php',
            
            // ============================================
            // MAIN APPLICATION PAGES
            // ============================================
            'dashboard' => 'dashboard.html',
            
            // ============================================
            // WIZARD PAGES (Onboarding)
            // ============================================
            'wizard' => 'chat_wizard.html',
            'chat_wizard' => 'chat_wizard.html',
            'token_wizard' => 'chat_token_wizard.html',
            'chat_token_wizard' => 'chat_token_wizard.html',
            'family_token_wizard' => 'chat_wizard_token_family.html',
            
            // ============================================
            // FAMILY TREE PAGES
            // ============================================
            'tree' => 'tree.html',
            'view_tree' => 'view_tree.html',
            'edit_tree' => 'edit_tree.html',
            'test_tree' => 'test_tree.html',
            
            // ============================================
            // PERSON MANAGEMENT
            // ============================================
            'edit_persons' => 'chatEdit_persons.html',
            'chat_edit_persons' => 'chatEdit_persons.html',
            'profile_persons' => 'profile_persons.html',
            'expand' => 'chat_expand.html',
            'chat_expand' => 'chat_expand.html',
            'expand_children' => 'chatExpand_children.html',
            'expand_partners' => 'chatExpand_partners.html',
            'expand_siblings' => 'chatExpand_siblings.html',
            
            // ============================================
            // INVITATIONS
            // ============================================
            'invites' => 'invites.html',
            'chat_invites' => 'chat_invites.html',
            'accept_invite' => 'accept_invite.html',
            
            // ============================================
            // PENDING DECISIONS (Duplicates)
            // ============================================
            'pd_view' => 'pd_view.html',
            'pd_tree' => 'pd_tree.html',
            'pending_decisions' => 'pd_view.html',
            
            // ============================================
            // PROFILE & SETTINGS
            // ============================================
            'profile' => 'profile.html',
            'settings' => 'settings.html',
            
            // ============================================
            // OTHER PAGES
            // ============================================
            'families' => 'families.html',
            'people' => 'people.html',
            'about' => 'about.html',
            'terms' => 'terms.html',
            'public' => 'public.html',
            
            // ============================================
            // ADMIN PAGES
            // ============================================
            'admin_dashboard' => 'admin_dashboard.html',
            'admin_pending_people' => 'admin_pending_people.html',
            'admin' => 'admin_dashboard.html',
        ];
        
        // Check if the path matches any friendly URL
        if (isset($friendlyMap[$path])) {
            $targetPage = $friendlyMap[$path];
            
            // Special handling for logout
            if ($targetPage === 'LOGOUT_ACTION') {
                return $this->handleLogout();
            }
            
            $queryStr = $this->queryString ? '?' . $this->queryString : '';
            
            // Redirect to the full language-specific path
            header("Location: /pages/{$this->lang}/{$targetPage}{$queryStr}");
            exit;
        }
        
        return false; // Not a friendly URL, continue with other routing
    }
    
    /**
     * Check if current request is a logout request
     */
    private function isLogoutRequest() {
        // Check for friendly URL /logout
        $path = trim($this->requestUri, '/');
        if ($path === 'logout') {
            return true;
        }
        
        // Check for language-specific logout pages
        if (preg_match('#^/pages/(EN|MY)/auth/logout\.php$#', $this->requestUri)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle logout - destroy all sessions and redirect to landing page
     * Can be called from /logout, /pages/EN/auth/logout.php, or /pages/MY/auth/logout.php
     */
    private function handleLogout() {
        // Start session if not started (to destroy it)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Store language preference before destroying session (optional)
        $savedLang = $_SESSION['lang'] ?? null;
        
        // Destroy the session completely
        $_SESSION = array(); // Clear all session variables
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy session file
        session_destroy();
        
        // Clear all authentication cookies
        $cookiesToClear = ['user_id', 'auth_token', 'remember_me'];
        foreach ($cookiesToClear as $cookie) {
            if (isset($_COOKIE[$cookie])) {
                setcookie($cookie, '', time() - 3600, '/');
            }
        }
        
        // Optionally preserve language cookie (user preference)
        // Uncomment if you want to keep language preference after logout
        // if ($savedLang) {
        //     setcookie('lang', $savedLang, time() + (365 * 24 * 60 * 60), '/');
        // }
        
        // Redirect to landing page (root index.html with NO session)
        header("Location: /index.html");
        exit;
    }
    
    /**
     * Handle landing page display (NO SESSION)
     * This is the only page that works without session
     * User selects language OR clicks login/register to start session
     */
    private function handleLandingPage() {
        // Landing page has NO session - always show language selection
        // Do NOT check for logged in user here
        // Do NOT redirect authenticated users
        
        // Serve the pure landing page (no session, no language injection)
        $filePath = __DIR__ . '/index.html';
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 - Landing Page Not Found</h1></body></html>";
            return true;
        }
        
        // No headers, no language injection, no session - pure landing page
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Past date
        
        readfile($filePath);
        return true;
    }
    
    /**
     * Redirect old /auth/ to language-specific auth
     */
    private function redirectToLangAuth() {
        $authPath = str_replace('/auth/', '', $this->requestUri);
        $queryStr = $this->queryString ? '?' . $this->queryString : '';
        header("Location: /pages/{$this->lang}/auth/{$authPath}{$queryStr}");
        exit;
    }
    
    /**
     * Handle language-specific page requests
     */
    private function handleLangPage($page) {
        // Auth pages don't require login
        if (strpos($page, 'auth/') === 0) {
            return $this->handleAuthPage($page);
        }
        
        // All other pages require authentication
        if (!$this->userId) {
            $_SESSION['redirect_after_login'] = $this->requestUri . ($this->queryString ? '?' . $this->queryString : '');
            $queryStr = $this->queryString ? '?' . $this->queryString : '';
            header("Location: /pages/{$this->lang}/auth/login.php{$queryStr}");
            exit;
        }
        
        // Route authenticated user based on their state
        return $this->routeAuthenticatedUser($page);
    }
    
    /**
     * Handle authentication pages (login, register, etc.)
     */
    private function handleAuthPage($page) {
        $filePath = __DIR__ . "/pages/{$this->lang}/{$page}";
        
        // Check if file exists
        if (!file_exists($filePath)) {
            http_response_code(404);
            $this->servePage(__DIR__ . "/pages/{$this->lang}/404.html");
            return true;
        }
        
        // If already logged in, redirect to appropriate destination
        if ($this->userId) {
            return $this->redirectAuthenticatedUser();
        }
        
        // Handle token-based registration
        if (strpos($page, 'register') !== false || strpos($page, 'tok_register') !== false) {
            return $this->handleRegistrationFlow($filePath);
        }
        
        // Regular auth page
        $this->servePage($filePath);
        return true;
    }
    
    /**
     * Handle registration flow with token detection
     */
    private function handleRegistrationFlow($filePath) {
        $inviteToken = $_GET['invite_token'] ?? $_GET['token'] ?? null;
        $familyToken = $_GET['family_token'] ?? $_GET['ft'] ?? null;
        
        // Store tokens in session for post-registration redirect
        if ($inviteToken) {
            $_SESSION['pending_invite_token'] = $inviteToken;
        }
        if ($familyToken) {
            $_SESSION['pending_family_token'] = $familyToken;
        }
        
        $this->servePage($filePath);
        return true;
    }
    
    /**
     * Route authenticated user based on their state
     */
    private function routeAuthenticatedUser($requestedPage) {
        // Check if user is admin
        if ($this->isAdmin()) {
            // Allow admin to access any page
            return $this->servePagePath("/pages/{$this->lang}/{$requestedPage}");
        }
        
        // Check wizard completion status
        $wizardStatus = $this->getWizardStatus();
        
        // If requesting index.html, redirect based on state
        if ($requestedPage === 'index.html') {
            return $this->redirectAuthenticatedUser();
        }
        
        // If wizard not complete and not requesting wizard page, redirect to wizard
        if (!$wizardStatus['completed']) {
            $allowedPages = [
                'chat_wizard.html',
                'chat_token_wizard.html',
                'chat_wizard_token_family.html'
            ];
            
            if (!in_array($requestedPage, $allowedPages)) {
                return $this->redirectToWizard($wizardStatus);
            }
        }
        
        // Serve the requested page
        return $this->servePagePath("/pages/{$this->lang}/{$requestedPage}");
    }
    
    /**
     * Redirect authenticated user to appropriate destination
     */
    private function redirectAuthenticatedUser() {
        // Check for pending redirect
        if (isset($_SESSION['redirect_after_login'])) {
            $redirect = $_SESSION['redirect_after_login'];
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirect");
            exit;
        }
        
        // Check for pending tokens (post-registration)
        if (isset($_SESSION['pending_invite_token'])) {
            $token = $_SESSION['pending_invite_token'];
            unset($_SESSION['pending_invite_token']);
            header("Location: /pages/{$this->lang}/dashboard.html?invite_token=$token");
            exit;
        }
        
        if (isset($_SESSION['pending_family_token'])) {
            $token = $_SESSION['pending_family_token'];
            unset($_SESSION['pending_family_token']);
            header("Location: /pages/{$this->lang}/chat_token_wizard.html?family_token=$token");
            exit;
        }
        
        // Admin redirect
        if ($this->isAdmin()) {
            header("Location: /pages/{$this->lang}/admin_dashboard.html");
            exit;
        }
        
        // Check wizard status
        $wizardStatus = $this->getWizardStatus();
        
        if (!$wizardStatus['completed']) {
            return $this->redirectToWizard($wizardStatus);
        }
        
        // Wizard complete - go to dashboard
        header("Location: /pages/{$this->lang}/dashboard.html");
        exit;
    }
    
    /**
     * Redirect to appropriate wizard based on user state
     */
    private function redirectToWizard($wizardStatus) {
        $page = 'chat_wizard.html';
        $query = '';
        
        if ($wizardStatus['has_family_token']) {
            $page = 'chat_token_wizard.html';
            if (!empty($wizardStatus['family_token'])) {
                $query = '?family_token=' . urlencode($wizardStatus['family_token']);
            }
        } elseif ($wizardStatus['step'] > 0) {
            // Continue from last step
            $query = '?step=' . $wizardStatus['step'];
        }
        
        header("Location: /pages/{$this->lang}/{$page}{$query}");
        exit;
    }
    
    /**
     * Serve a page by relative path
     */
    private function servePagePath($relativePath) {
        $filePath = __DIR__ . $relativePath;
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            $this->servePage(__DIR__ . "/pages/{$this->lang}/404.html");
            return true;
        }
        
        $this->servePage($filePath);
        return true;
    }
    
    /**
     * Serve a page with language injection
     */
    private function servePage($filePath) {
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 - Page Not Found</h1></body></html>";
            return;
        }
        
        // Set headers
        header("Content-Language: " . strtolower($this->lang));
        
        // Read and potentially modify content
        $content = file_get_contents($filePath);
        
        // Inject language into HTML lang attribute if not present
        if (strpos($content, '<html') !== false && strpos($content, 'lang=') === false) {
            $content = preg_replace('/<html\b/i', '<html lang="' . strtolower($this->lang) . '"', $content, 1);
        }
        
        // Inject language data attribute into body
        if (strpos($content, '<body') !== false && strpos($content, 'data-lang') === false) {
            $content = preg_replace('/<body\b/i', '<body data-lang="' . $this->lang . '"', $content, 1);
        }
        
        echo $content;
    }
    
    /**
     * Check if user is admin
     */
    private function isAdmin() {
        if (!$this->userId) return false;
        
        try {
            $stmt = $this->db->prepare("
                SELECT pwa_admin FROM users WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($result['pwa_admin']);
        } catch (Exception $e) {
            error_log("Error checking admin status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get wizard completion status for user
     */
    private function getWizardStatus() {
        if (!$this->userId) {
            return ['completed' => false, 'step' => 0, 'has_family_token' => false, 'family_token' => null];
        }
        
        try {
            // Check if user has families_id set (indicates family membership)
            $stmt = $this->db->prepare("
                SELECT families_id FROM users WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $hasFamily = !empty($result['families_id']);
            
            // Check for pending family token
            $hasFamilyToken = isset($_SESSION['pending_family_token']) || 
                            isset($_GET['family_token']) || 
                            isset($_GET['ft']);
            
            $familyToken = $_SESSION['pending_family_token'] ?? $_GET['family_token'] ?? $_GET['ft'] ?? null;
            
            return [
                'completed' => $hasFamily,
                'step' => 0, // Could track actual wizard step in user settings table
                'has_family_token' => $hasFamilyToken,
                'family_token' => $familyToken
            ];
        } catch (Exception $e) {
            error_log("Error getting wizard status: " . $e->getMessage());
            return ['completed' => false, 'step' => 0, 'has_family_token' => false, 'family_token' => null];
        }
    }
    
    /**
     * Handle default/unknown routes
     */
    private function handleDefault() {
        if ($this->userId) {
            return $this->redirectAuthenticatedUser();
        }
        
        // Redirect to landing
        header("Location: /");
        exit;
    }
    
    /**
     * Get current language
     */
    public function getLang() {
        return $this->lang;
    }
}

// Initialize and run router
try {
    $router = new SmartRouter();
    $handled = $router->route();
    
    // If router returned false, let Apache/PHP handle the request
    // This is for static assets, API endpoints, etc.
    if ($handled === false) {
        return false;
    }
} catch (Exception $e) {
    error_log("Router error: " . $e->getMessage());
    http_response_code(500);
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body><h1>500 - Internal Server Error</h1></body></html>";
}
