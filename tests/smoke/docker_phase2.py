"""End-to-end smoke test against the CI-only Compose services."""

import http.cookiejar
import json
import urllib.parse
import urllib.request
from html.parser import HTMLParser

BASE = 'http://localhost:8080'
MAILPIT = 'http://127.0.0.1:8025'


class FormParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.csrf = None
        self.invitation = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name') == '_token':
            self.csrf = attrs.get('value')
        if tag == 'a' and '/invitation/' in attrs.get('href', ''):
            self.invitation = attrs['href']


def read(opener, url, data=None):
    payload = None if data is None else urllib.parse.urlencode(data).encode()
    with opener.open(url, data=payload, timeout=20) as response:
        return response.read().decode()


def token(html):
    parser = FormParser()
    parser.feed(html)
    assert parser.csrf, 'CSRF token missing'
    return parser.csrf


browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
login = read(browser, BASE + '/login')
dashboard = read(browser, BASE + '/login', {'_token': token(login), 'email': 'admin@example.com', 'password': 'CI-admin-password-123'})
assert 'Tableau de bord' in dashboard, 'Admin login failed'
users = read(browser, BASE + '/users')
read(browser, BASE + '/users', {'_token': token(users), 'name': 'Test Invite', 'email': 'invite@example.com', 'role': 'client'})
messages = json.loads(read(browser, MAILPIT + '/api/v1/messages'))['messages']
message = next(m for m in messages if m['Subject'] == 'Invitation à rejoindre Archi TN')
email = json.loads(read(browser, MAILPIT + '/api/v1/message/' + message['ID']))
parser = FormParser()
parser.feed(email['HTML'])
assert parser.invitation, 'Invitation link missing from email'
users = read(browser, BASE + '/users')
read(browser, BASE + '/logout', {'_token': token(users)})
invite = read(browser, parser.invitation)
login = read(browser, parser.invitation, {'_token': token(invite), 'password': 'CI-invited-password-123', 'password_confirmation': 'CI-invited-password-123'})
assert 'Connexion' in login, 'Invitation was not completed'
dashboard = read(browser, BASE + '/login', {'_token': token(login), 'email': 'invite@example.com', 'password': 'CI-invited-password-123'})
assert 'Bienvenue dans votre espace personnel' in dashboard, 'Client dashboard missing'
print('Invitation, Mailpit and client login succeeded')
