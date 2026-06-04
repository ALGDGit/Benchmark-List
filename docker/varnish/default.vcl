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
        if (req.http.X-Ban-All-Tags == "1") {
            ban("obj.http.X-Cache-Tags ~ .");
        }
        if (req.http.X-Ban-Tag) {
            /* Tags are #delimited# in X-Cache-Tags; # is not a regex metacharacter in ban expressions */
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
    if (bereq.url ~ "^/api/lists/" || bereq.url ~ "^/api/categories/") {
        unset beresp.http.Set-Cookie;
        if (beresp.http.Cache-Control ~ "no-cache|private") {
            set beresp.ttl = 0s;
            set beresp.uncacheable = true;
        } else {
            set beresp.ttl = 1h;
            /* grace must be 0 or banned objects keep being served as stale */
            set beresp.grace = 0s;
        }
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
    unset resp.http.X-Cache-Tags;
}
