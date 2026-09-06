package com.azariresidences.app;

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.ContentResolver;
import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.graphics.PorterDuff;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.NetworkInfo;
import android.net.NetworkRequest;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Build;
import android.os.Bundle;
import android.os.Message;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.view.WindowInsets;
import android.webkit.CookieManager;
import android.webkit.SslErrorHandler;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import java.io.BufferedInputStream;
import java.io.BufferedOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.Locale;

public final class MainActivity extends Activity {
    private static final String HOME_URL = "https://theazariresidence.com/login";
    private static final int AZARI_DARK = Color.rgb(12, 43, 36);
    private static final int AZARI_GREEN = Color.rgb(18, 58, 48);
    private static final int AZARI_BRASS = Color.rgb(181, 138, 74);
    private static final int REQUEST_FILE_CHOOSER = 4101;
    private static final int REQUEST_SAVE_DOWNLOAD = 4102;

    private FrameLayout root;
    private WebView webView;
    private View loadingOverlay;
    private View offlineOverlay;
    private View errorOverlay;
    private TextView errorTitle;
    private TextView errorMessage;
    private ConnectivityManager connectivityManager;
    private ConnectivityManager.NetworkCallback networkCallback;
    private ValueCallback<Uri[]> fileChooserCallback;

    private String pendingDownloadUrl;
    private String pendingDownloadMime;
    private String pendingDownloadUserAgent;
    private boolean pageHasLoaded;
    private boolean lastKnownOnline;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        configureWindow();
        buildInterface();
        configureWebView();
        registerConnectivityCallback();

        if (Build.VERSION.SDK_INT >= 33) {
            getOnBackInvokedDispatcher().registerOnBackInvokedCallback(
                    android.window.OnBackInvokedDispatcher.PRIORITY_DEFAULT,
                    this::handleBack
            );
        }

        syncConnectionState(true);
    }

    private void configureWindow() {
        Window window = getWindow();
        window.setStatusBarColor(AZARI_DARK);
        window.setNavigationBarColor(AZARI_DARK);
        if (Build.VERSION.SDK_INT >= 29) {
            window.setStatusBarContrastEnforced(false);
            window.setNavigationBarContrastEnforced(false);
        }
        window.getDecorView().setSystemUiVisibility(0);
    }

    private void buildInterface() {
        root = new FrameLayout(this);
        root.setBackgroundColor(AZARI_DARK);

        root.setOnApplyWindowInsetsListener((view, insets) -> {
            view.setPadding(
                    0,
                    insets.getSystemWindowInsetTop(),
                    0,
                    insets.getSystemWindowInsetBottom()
            );
            return insets;
        });

        webView = new WebView(this);
        webView.setBackgroundColor(AZARI_DARK);
        root.addView(webView, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
        ));

        loadingOverlay = buildLoadingOverlay();
        root.addView(loadingOverlay, matchParent());

        offlineOverlay = buildOfflineOverlay();
        offlineOverlay.setVisibility(View.GONE);
        root.addView(offlineOverlay, matchParent());

        errorOverlay = buildErrorOverlay();
        errorOverlay.setVisibility(View.GONE);
        root.addView(errorOverlay, matchParent());

        setContentView(root);
    }

    private FrameLayout.LayoutParams matchParent() {
        return new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
        );
    }

    private View buildLoadingOverlay() {
        LinearLayout box = centeredColumn();
        box.setBackgroundColor(AZARI_DARK);
        box.setPadding(dp(32), dp(32), dp(32), dp(32));

        ImageView logo = new ImageView(this);
        logo.setImageResource(R.drawable.azari_logo);
        logo.setScaleType(ImageView.ScaleType.CENTER_INSIDE);
        logo.setColorFilter(Color.WHITE, PorterDuff.Mode.SRC_IN);
        LinearLayout.LayoutParams logoParams = new LinearLayout.LayoutParams(dp(94), dp(94));
        logoParams.bottomMargin = dp(24);
        box.addView(logo, logoParams);

        ProgressBar progress = new ProgressBar(this);
        progress.getIndeterminateDrawable().setColorFilter(AZARI_BRASS, PorterDuff.Mode.SRC_IN);
        box.addView(progress, new LinearLayout.LayoutParams(dp(42), dp(42)));

        TextView text = bodyText("Preparing your Azari experience…", 14, 0.72f);
        LinearLayout.LayoutParams textParams = new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
        );
        textParams.topMargin = dp(18);
        box.addView(text, textParams);
        return box;
    }

    private View buildOfflineOverlay() {
        LinearLayout box = centeredColumn();
        box.setBackgroundColor(AZARI_DARK);
        box.setPadding(dp(24), dp(28), dp(24), dp(28));

        ImageView illustration = new ImageView(this);
        illustration.setImageResource(R.drawable.offline_azari);
        illustration.setScaleType(ImageView.ScaleType.CENTER_INSIDE);
        box.addView(illustration, new LinearLayout.LayoutParams(dp(330), dp(330)));

        Button retry = actionButton("TRY AGAIN");
        LinearLayout.LayoutParams retryParams = new LinearLayout.LayoutParams(dp(176), dp(50));
        retryParams.topMargin = dp(12);
        box.addView(retry, retryParams);
        retry.setOnClickListener(v -> syncConnectionState(true));
        return box;
    }

    private View buildErrorOverlay() {
        LinearLayout box = centeredColumn();
        box.setBackgroundColor(AZARI_DARK);
        box.setPadding(dp(34), dp(34), dp(34), dp(34));

        ImageView logo = new ImageView(this);
        logo.setImageResource(R.drawable.azari_logo);
        logo.setColorFilter(Color.WHITE, PorterDuff.Mode.SRC_IN);
        logo.setScaleType(ImageView.ScaleType.CENTER_INSIDE);
        box.addView(logo, new LinearLayout.LayoutParams(dp(82), dp(82)));

        errorTitle = headingText("Unable to load this page", 25);
        LinearLayout.LayoutParams titleParams = wrapContent();
        titleParams.topMargin = dp(24);
        box.addView(errorTitle, titleParams);

        errorMessage = bodyText("Please try again. If the problem continues, check your connection.", 14, 0.72f);
        errorMessage.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams messageParams = new LinearLayout.LayoutParams(
                Math.min(getResources().getDisplayMetrics().widthPixels - dp(68), dp(420)),
                ViewGroup.LayoutParams.WRAP_CONTENT
        );
        messageParams.topMargin = dp(12);
        box.addView(errorMessage, messageParams);

        Button retry = actionButton("RETRY");
        LinearLayout.LayoutParams retryParams = new LinearLayout.LayoutParams(dp(160), dp(50));
        retryParams.topMargin = dp(28);
        box.addView(retry, retryParams);
        retry.setOnClickListener(v -> {
            errorOverlay.setVisibility(View.GONE);
            syncConnectionState(true);
        });
        return box;
    }

    private LinearLayout centeredColumn() {
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setGravity(Gravity.CENTER);
        return box;
    }

    private LinearLayout.LayoutParams wrapContent() {
        return new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
        );
    }

    private TextView headingText(String value, int sp) {
        TextView view = new TextView(this);
        view.setText(value);
        view.setTextColor(Color.WHITE);
        view.setTextSize(sp);
        view.setTypeface(Typeface.create("serif", Typeface.BOLD));
        view.setGravity(Gravity.CENTER);
        return view;
    }

    private TextView bodyText(String value, int sp, float alpha) {
        TextView view = new TextView(this);
        view.setText(value);
        view.setTextColor(Color.argb(Math.round(255 * alpha), 255, 255, 255));
        view.setTextSize(sp);
        view.setGravity(Gravity.CENTER);
        return view;
    }

    private Button actionButton(String text) {
        Button button = new Button(this);
        button.setText(text);
        button.setTextColor(AZARI_DARK);
        button.setTextSize(12);
        button.setTypeface(Typeface.DEFAULT_BOLD);
        button.setAllCaps(false);
        button.setPadding(dp(18), 0, dp(18), 0);
        GradientDrawable background = new GradientDrawable();
        background.setColor(AZARI_BRASS);
        background.setCornerRadius(dp(14));
        button.setBackground(background);
        return button;
    }

    private void configureWebView() {
        WebView.setWebContentsDebuggingEnabled(false);
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(true);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setSupportZoom(false);
        settings.setLoadWithOverviewMode(false);
        settings.setUseWideViewPort(false);
        settings.setJavaScriptCanOpenWindowsAutomatically(true);
        settings.setSupportMultipleWindows(true);
        if (Build.VERSION.SDK_INT >= 21) {
            settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        }
        if (Build.VERSION.SDK_INT >= 26) {
            settings.setSafeBrowsingEnabled(true);
        }

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        if (Build.VERSION.SDK_INT >= 21) {
            cookies.setAcceptThirdPartyCookies(webView, true);
        }

        webView.setWebViewClient(new AzariWebViewClient());
        webView.setWebChromeClient(new AzariWebChromeClient());
        webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) ->
                beginDownload(url, userAgent, contentDisposition, mimeType)
        );
    }

    private final class AzariWebViewClient extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            return routeUrl(request.getUrl().toString());
        }

        @Override
        public boolean shouldOverrideUrlLoading(WebView view, String url) {
            return routeUrl(url);
        }

        @Override
        public void onPageStarted(WebView view, String url, Bitmap favicon) {
            super.onPageStarted(view, url, favicon);
            if (isOnline()) {
                errorOverlay.setVisibility(View.GONE);
                offlineOverlay.setVisibility(View.GONE);
                loadingOverlay.setVisibility(View.VISIBLE);
            }
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            pageHasLoaded = true;
            if (isOnline()) {
                loadingOverlay.setVisibility(View.GONE);
                offlineOverlay.setVisibility(View.GONE);
                errorOverlay.setVisibility(View.GONE);
            }
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            super.onReceivedError(view, request, error);
            if (request.isForMainFrame()) {
                if (!isOnline()) {
                    showOffline();
                } else {
                    showError("Unable to load this page", "The page could not be reached securely. Please try again.");
                }
            }
        }

        @Override
        public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
            super.onReceivedError(view, errorCode, description, failingUrl);
            if (!isOnline()) {
                showOffline();
            }
        }

        @Override
        public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
            handler.cancel();
            showError(
                    "Secure connection problem",
                    "Azari stopped this page because its security certificate could not be verified."
            );
        }
    }

    private final class AzariWebChromeClient extends WebChromeClient {
        @Override
        public void onProgressChanged(WebView view, int newProgress) {
            super.onProgressChanged(view, newProgress);
            if (newProgress >= 100 && isOnline() && errorOverlay.getVisibility() != View.VISIBLE) {
                loadingOverlay.setVisibility(View.GONE);
            }
        }

        @Override
        public boolean onShowFileChooser(
                WebView webView,
                ValueCallback<Uri[]> filePathCallback,
                FileChooserParams fileChooserParams
        ) {
            if (fileChooserCallback != null) {
                fileChooserCallback.onReceiveValue(null);
            }
            fileChooserCallback = filePathCallback;
            try {
                Intent chooser = fileChooserParams.createIntent();
                chooser.addCategory(Intent.CATEGORY_OPENABLE);
                startActivityForResult(chooser, REQUEST_FILE_CHOOSER);
                return true;
            } catch (ActivityNotFoundException exception) {
                fileChooserCallback = null;
                Toast.makeText(MainActivity.this, "No file picker is available on this device.", Toast.LENGTH_LONG).show();
                return false;
            }
        }

        @Override
        public boolean onCreateWindow(WebView view, boolean isDialog, boolean isUserGesture, Message resultMsg) {
            WebView popup = new WebView(MainActivity.this);
            popup.getSettings().setJavaScriptEnabled(true);
            popup.setWebViewClient(new WebViewClient() {
                @Override
                public void onPageStarted(WebView child, String url, Bitmap favicon) {
                    child.stopLoading();
                    handlePopupUrl(url);
                }

                @Override
                public boolean shouldOverrideUrlLoading(WebView child, String url) {
                    handlePopupUrl(url);
                    return true;
                }
            });
            WebView.WebViewTransport transport = (WebView.WebViewTransport) resultMsg.obj;
            transport.setWebView(popup);
            resultMsg.sendToTarget();
            return true;
        }
    }

    private boolean routeUrl(String rawUrl) {
        if (rawUrl == null || rawUrl.trim().isEmpty()) {
            return true;
        }

        Uri uri;
        try {
            uri = Uri.parse(rawUrl);
        } catch (Exception exception) {
            return true;
        }

        String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase(Locale.US);

        if ("https".equals(scheme)) {
            if (isExternalServiceUrl(uri)) {
                openExternal(rawUrl);
                return true;
            }
            return false;
        }

        if ("http".equals(scheme)) {
            showError("Secure connection required", "Azari only permits encrypted HTTPS connections.");
            return true;
        }

        if ("tel".equals(scheme)
                || "mailto".equals(scheme)
                || "sms".equals(scheme)
                || "smsto".equals(scheme)
                || "geo".equals(scheme)
                || "market".equals(scheme)
                || "whatsapp".equals(scheme)) {
            openExternal(rawUrl);
            return true;
        }

        if ("intent".equals(scheme)) {
            openIntentScheme(rawUrl);
            return true;
        }

        if ("blob".equals(scheme)
                || "data".equals(scheme)
                || "about".equals(scheme)
                || "javascript".equals(scheme)) {
            return false;
        }

        return true;
    }

    private boolean isExternalServiceUrl(Uri uri) {
        String host = uri.getHost();
        if (host == null) {
            return false;
        }
        host = host.toLowerCase(Locale.US);
        String path = uri.getPath() == null ? "" : uri.getPath().toLowerCase(Locale.US);
        return host.equals("wa.me")
                || host.equals("api.whatsapp.com")
                || host.equals("maps.app.goo.gl")
                || host.equals("maps.google.com")
                || ((host.equals("google.com") || host.equals("www.google.com")) && path.startsWith("/maps"));
    }

    private void handlePopupUrl(String url) {
        if (url == null) {
            return;
        }
        Uri uri = Uri.parse(url);
        String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase(Locale.US);
        if ("https".equals(scheme) && !isExternalServiceUrl(uri)) {
            webView.loadUrl(url);
            return;
        }
        routeUrl(url);
    }

    private void openExternal(String url) {
        try {
            Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
            intent.addCategory(Intent.CATEGORY_BROWSABLE);
            startActivity(intent);
        } catch (ActivityNotFoundException exception) {
            Toast.makeText(this, "No compatible app is installed for this link.", Toast.LENGTH_LONG).show();
        }
    }

    private void openIntentScheme(String url) {
        try {
            Intent intent = Intent.parseUri(url, Intent.URI_INTENT_SCHEME);
            intent.addCategory(Intent.CATEGORY_BROWSABLE);
            intent.setComponent(null);
            intent.setSelector(null);
            try {
                startActivity(intent);
            } catch (ActivityNotFoundException exception) {
                String fallback = intent.getStringExtra("browser_fallback_url");
                if (fallback != null && fallback.startsWith("https://")) {
                    webView.loadUrl(fallback);
                } else {
                    Toast.makeText(this, "The requested app is not installed.", Toast.LENGTH_LONG).show();
                }
            }
        } catch (Exception exception) {
            Toast.makeText(this, "Unable to open this link.", Toast.LENGTH_LONG).show();
        }
    }

    private void beginDownload(String url, String userAgent, String contentDisposition, String mimeType) {
        if (url == null || !url.startsWith("https://")) {
            Toast.makeText(this, "Only secure HTTPS downloads are allowed.", Toast.LENGTH_LONG).show();
            return;
        }

        pendingDownloadUrl = url;
        pendingDownloadMime = (mimeType == null || mimeType.trim().isEmpty()) ? "application/octet-stream" : mimeType;
        pendingDownloadUserAgent = userAgent;
        String fileName = URLUtil.guessFileName(url, contentDisposition, pendingDownloadMime);

        Intent save = new Intent(Intent.ACTION_CREATE_DOCUMENT);
        save.addCategory(Intent.CATEGORY_OPENABLE);
        save.setType(pendingDownloadMime);
        save.putExtra(Intent.EXTRA_TITLE, fileName);
        try {
            startActivityForResult(save, REQUEST_SAVE_DOWNLOAD);
        } catch (ActivityNotFoundException exception) {
            clearPendingDownload();
            Toast.makeText(this, "No document provider is available to save this file.", Toast.LENGTH_LONG).show();
        }
    }

    private void downloadToUri(Uri destination) {
        final String url = pendingDownloadUrl;
        final String mime = pendingDownloadMime;
        final String userAgent = pendingDownloadUserAgent;
        clearPendingDownload();

        Toast.makeText(this, "Downloading securely…", Toast.LENGTH_SHORT).show();

        new Thread(() -> {
            HttpURLConnection connection = null;
            try {
                connection = (HttpURLConnection) new URL(url).openConnection();
                connection.setConnectTimeout(20000);
                connection.setReadTimeout(60000);
                connection.setInstanceFollowRedirects(true);
                connection.setRequestProperty("Accept", mime == null ? "*/*" : mime);
                if (userAgent != null && !userAgent.isEmpty()) {
                    connection.setRequestProperty("User-Agent", userAgent);
                } else {
                    connection.setRequestProperty("User-Agent", webView.getSettings().getUserAgentString());
                }
                String cookie = CookieManager.getInstance().getCookie(url);
                if (cookie != null && !cookie.isEmpty()) {
                    connection.setRequestProperty("Cookie", cookie);
                }
                connection.connect();

                int code = connection.getResponseCode();
                if (code < 200 || code >= 300) {
                    throw new IllegalStateException("HTTP " + code);
                }

                ContentResolver resolver = getContentResolver();
                try (InputStream input = new BufferedInputStream(connection.getInputStream());
                     OutputStream output = new BufferedOutputStream(resolver.openOutputStream(destination, "w"))) {
                    if (output == null) {
                        throw new IllegalStateException("Could not open destination");
                    }
                    byte[] buffer = new byte[32 * 1024];
                    int count;
                    while ((count = input.read(buffer)) != -1) {
                        output.write(buffer, 0, count);
                    }
                    output.flush();
                }

                runOnUiThread(() -> Toast.makeText(
                        MainActivity.this,
                        "Download saved.",
                        Toast.LENGTH_LONG
                ).show());
            } catch (Exception exception) {
                runOnUiThread(() -> Toast.makeText(
                        MainActivity.this,
                        "Download failed. Please try again.",
                        Toast.LENGTH_LONG
                ).show());
            } finally {
                if (connection != null) {
                    connection.disconnect();
                }
            }
        }, "AzariSecureDownload").start();
    }

    private void clearPendingDownload() {
        pendingDownloadUrl = null;
        pendingDownloadMime = null;
        pendingDownloadUserAgent = null;
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);

        if (requestCode == REQUEST_FILE_CHOOSER) {
            if (fileChooserCallback != null) {
                Uri[] results = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
                fileChooserCallback.onReceiveValue(results);
                fileChooserCallback = null;
            }
            return;
        }

        if (requestCode == REQUEST_SAVE_DOWNLOAD) {
            if (resultCode == RESULT_OK && data != null && data.getData() != null && pendingDownloadUrl != null) {
                downloadToUri(data.getData());
            } else {
                clearPendingDownload();
            }
        }
    }

    private void registerConnectivityCallback() {
        connectivityManager = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (connectivityManager == null) {
            return;
        }

        networkCallback = new ConnectivityManager.NetworkCallback() {
            @Override
            public void onAvailable(Network network) {
                runOnUiThread(() -> syncConnectionState(false));
            }

            @Override
            public void onCapabilitiesChanged(Network network, NetworkCapabilities capabilities) {
                runOnUiThread(() -> syncConnectionState(false));
            }

            @Override
            public void onLost(Network network) {
                runOnUiThread(() -> syncConnectionState(false));
            }
        };

        try {
            if (Build.VERSION.SDK_INT >= 24) {
                connectivityManager.registerDefaultNetworkCallback(networkCallback);
            } else {
                NetworkRequest request = new NetworkRequest.Builder()
                        .addCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                        .build();
                connectivityManager.registerNetworkCallback(request, networkCallback);
            }
        } catch (Exception ignored) {
            // onResume() still performs a direct connectivity check.
        }
    }

    private boolean isOnline() {
        if (connectivityManager == null) {
            connectivityManager = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        }
        if (connectivityManager == null) {
            return false;
        }

        if (Build.VERSION.SDK_INT >= 23) {
            Network network = connectivityManager.getActiveNetwork();
            if (network == null) {
                return false;
            }
            NetworkCapabilities capabilities = connectivityManager.getNetworkCapabilities(network);
            return capabilities != null
                    && capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
                    && capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED);
        }

        NetworkInfo info = connectivityManager.getActiveNetworkInfo();
        return info != null && info.isConnected();
    }

    private void syncConnectionState(boolean forceReload) {
        boolean online = isOnline();
        boolean reconnected = online && !lastKnownOnline;
        lastKnownOnline = online;

        if (!online) {
            showOffline();
            return;
        }

        offlineOverlay.setVisibility(View.GONE);
        errorOverlay.setVisibility(View.GONE);

        if (!pageHasLoaded || webView.getUrl() == null || webView.getUrl().trim().isEmpty()) {
            loadingOverlay.setVisibility(View.VISIBLE);
            webView.loadUrl(HOME_URL);
            return;
        }

        if (forceReload || reconnected) {
            loadingOverlay.setVisibility(View.VISIBLE);
            webView.reload();
        }
    }

    private void showOffline() {
        loadingOverlay.setVisibility(View.GONE);
        errorOverlay.setVisibility(View.GONE);
        offlineOverlay.setVisibility(View.VISIBLE);
        webView.stopLoading();
    }

    private void showError(String title, String message) {
        loadingOverlay.setVisibility(View.GONE);
        offlineOverlay.setVisibility(View.GONE);
        errorTitle.setText(title);
        errorMessage.setText(message);
        errorOverlay.setVisibility(View.VISIBLE);
        webView.stopLoading();
    }

    private void handleBack() {
        if (offlineOverlay.getVisibility() == View.VISIBLE || errorOverlay.getVisibility() == View.VISIBLE) {
            finish();
            return;
        }
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
        } else {
            finish();
        }
    }

    @Override
    public void onBackPressed() {
        if (Build.VERSION.SDK_INT < 33) {
            handleBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (root != null) {
            syncConnectionState(false);
        }
    }

    @Override
    protected void onDestroy() {
        if (connectivityManager != null && networkCallback != null) {
            try {
                connectivityManager.unregisterNetworkCallback(networkCallback);
            } catch (Exception ignored) {
            }
        }
        if (fileChooserCallback != null) {
            fileChooserCallback.onReceiveValue(null);
            fileChooserCallback = null;
        }
        if (webView != null) {
            webView.stopLoading();
            webView.setWebChromeClient(null);
            webView.setWebViewClient(null);
            webView.destroy();
        }
        super.onDestroy();
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }
}
