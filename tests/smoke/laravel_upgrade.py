"""Exercise persisted sessions and accounts across a Laravel 11 → 12 upgrade."""

import http.cookiejar
import os
import sys
import tempfile
import urllib.parse
import urllib.request
from html.parser import HTMLParser

BASE = 'http://localhost:8080'
COOKIE_PATH = os.path.join(tempfile.gettempdir(), 'archi-upgrade-cookies.txt')


class CsrfParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.value = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name') == '_token':
            self.value = attrs.get('value')


def token(html):
    parser = CsrfParser()
    parser.feed(html)
    assert parser.value, 'CSRF token missing'
    return parser.value


def read(opener, path, data=None):
    payload = None if data is None else urllib.parse.urlencode(data).encode()
    with opener.open(BASE + path, data=payload, timeout=20) as response:
        return response.read().decode()


def login(browser):
    page = read(browser, '/login')
    return read(browser, '/login', {'_token': token(page), 'email': 'invite@example.com', 'password': 'CI-invited-password-123'})


cookies = http.cookiejar.LWPCookieJar(COOKIE_PATH)
if sys.argv[1] == 'verify':
    cookies.load(ignore_discard=True, ignore_expires=True)
browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))

if sys.argv[1] == 'seed':
    login(browser)
    profile = read(browser, '/profile')
    page = read(browser, '/profile', {'_token': token(profile), '_method': 'PATCH', 'name': 'Test Invite', 'phone': '12345678', 'locale': 'ar'})
    assert 'lang="ar" dir="rtl"' in page, 'Locale was not saved before upgrade'
    cookies.save(ignore_discard=True, ignore_expires=True)
elif sys.argv[1] == 'verify':
    dashboard = read(browser, '/dashboard')
    assert 'lang="ar" dir="rtl"' in dashboard, 'Existing authenticated session was not preserved'
    profile = read(browser, '/profile')
    assert '12345678' in profile, 'Existing profile data was not preserved'
    read(browser, '/logout', {'_token': token(profile)})
    dashboard = login(browser)
    assert 'lang="ar" dir="rtl"' in dashboard, 'Account password or preferred locale was not preserved'
else:
    raise ValueError('Expected seed or verify')

print('Upgrade persistence check: ' + sys.argv[1])
