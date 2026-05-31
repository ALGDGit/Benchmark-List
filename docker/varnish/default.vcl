vcl 4.1;

backend default {
    .host = "web";
    .port = "80";
    .connect_timeout = 10s;
    .first_byte_timeout = 120s;
    .between_bytes_timeout = 120s;
}

acl purge {
    "web";
    "localhost";
    "127.0.0.1";
    "172.16.0.0"/12;
    "10.0.0.0"/8;
}

sub vcl_recv {
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        if (req.http.X-Ban-Slot ~ "^(10|[1-9])$") {
            ban("req.url ~ ^/api/lists/" + req.http.X-Ban-Slot + "($|\\?)");
        }
        if (req.http.X-Ban-Category) {
            ban("req.url ~ ^/api/categories/" + req.http.X-Ban-Category + "/items($|\\?)");
        }
        return (synth(200, "Ban added"));
    }

    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        return (purge);
    }

    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    if (req.url ~ "^/api/lists/" || req.url ~ "^/api/categories/") {
        unset req.http.Cookie;
        return (hash);
    }

    return (pass);
}

sub vcl_backend_response {
    if (bereq.url ~ "^/api/lists/") {
        unset beresp.http.Set-Cookie;
        if (beresp.http.Cache-Control ~ "no-cache|private") {
            set beresp.ttl = 0s;
            set beresp.uncacheable = true;
        } else {
            set beresp.ttl = 1h;
            set beresp.grace = 5m;
        }
        if (beresp.http.X-List-Slot) {
            set beresp.http.X-Cache-Tag = "list-" + beresp.http.X-List-Slot;
        }
    }

    if (bereq.url ~ "^/api/categories/") {
        unset beresp.http.Set-Cookie;
        if (beresp.http.Cache-Control ~ "no-cache|private") {
            set beresp.ttl = 0s;
            set beresp.uncacheable = true;
        } else {
            set beresp.ttl = 1h;
            set beresp.grace = 5m;
        }
        if (beresp.http.X-Category-Slug) {
            set beresp.http.X-Cache-Tag = "category-" + beresp.http.X-Category-Slug;
        }
    }
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
}
