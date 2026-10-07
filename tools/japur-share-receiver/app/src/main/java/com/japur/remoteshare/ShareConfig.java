package com.japur.remoteshare;

import android.content.Context;
import android.content.SharedPreferences;

final class ShareConfig {
    private static final String PREF = "japur_share";
    private static final String BASE = "base_url";
    private static final String KEY = "share_key";
    static String baseUrl(Context c) { return c.getSharedPreferences(PREF,0).getString(BASE,"https://remote.jayapurnama.com/"); }
    static String key(Context c) { return c.getSharedPreferences(PREF,0).getString(KEY,""); }
    static void save(Context c,String base,String key) { SharedPreferences.Editor e=c.getSharedPreferences(PREF,0).edit(); e.putString(BASE,normalize(base)); e.putString(KEY,key.trim()); e.apply(); }
    static String normalize(String s) { s=s==null?"":s.trim(); while(s.endsWith("/")) s=s.substring(0,s.length()-1); return s; }
    private ShareConfig(){}
}