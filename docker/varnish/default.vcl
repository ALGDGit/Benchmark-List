vcl 4.1;

# =============================================================================
# Benchmark List — Varnish VCL
# =============================================================================
# Caches GET /api/lists/* and GET /api/categories/* (JSON list endpoints).
# Invalidation is tag-based via HTTP BAN (see App\Service\VarnishPurger).
# Tags are stored on cached objects as X-Cache-Tags with # delimiters, e.g.:
#   #category-sopas#category-sopas-page-1#
# BAN expression: obj.http.X-Cache-Tags ~ #category-sopas-page-1#
# =============================================================================

backend default {
    .host = "web";
    .port = "80";
    .connect_timeout = 10s;
    .first_byte_timeout = 120s;
    .between_bytes_timeout = 120s;
}

# IPs allowed to send BAN / PURGE (Symfony container + Docker networks)
acl purge {
    "web";
    "localhost";
    "127.0.0.1";
    "172.16.0.0"/12;
    "10.0.0.0"/8;
}

sub vcl_recv {
    # --- Invalidation (called from PHP VarnishPurger) -------------------------
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        # Purge every tagged API object (admin "Purge all")
        if (req.http.X-Ban-All-Tags == "1") {
            ban("obj.http.X-Cache-Tags ~ .");
        }
        # Single tag: #tag# delimiters avoid list-1 vs list-10 false positives
        if (req.http.X-Ban-Tag) {
            ban("obj.http.X-Cache-Tags ~ #" + req.http.X-Ban-Tag + "#");
        }
        return (synth(200, "Ban added"));
    }

    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        return (purge);
    }

    # --- Only cache safe read methods -----------------------------------------
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # List + category item APIs are public JSON; strip cookies so Varnish can hash
    if (req.url ~ "^/api/lists/" || req.url ~ "^/api/categories/") {
        unset req.http.Cookie;
        return (hash);
    }

    # Everything else (HTML, /api/search, /api/activity, admin) → origin every time
    return (pass);
}

sub vcl_backend_response {
    if (bereq.url ~ "^/api/lists/" || bereq.url ~ "^/api/categories/") {
        unset beresp.http.Set-Cookie;
        if (beresp.http.Cache-Control ~ "no-cache|private") {
            set beresp.ttl = 0s;
            set beresp.uncacheable = true;
        } else {
            # Origin sends s-maxage=3600; browser max-age=0 (see API controllers)
            set beresp.ttl = 1h;
            # grace=0: banned objects must not be served stale after BAN
            set beresp.grace = 0s;
        }
        # Persist tags on the cached object for ban lurker matching
        if (beresp.http.X-Cache-Tags) {
            set beresp.http.X-Cache-Tags = beresp.http.X-Cache-Tags;
        }
    }
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
    # Tags are internal; UI reads X-Cache only
    unset resp.http.X-Cache-Tags;
}
