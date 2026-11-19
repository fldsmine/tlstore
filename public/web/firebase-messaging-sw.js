importScripts('https://www.gstatic.com/firebasejs/13.1.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/13.1.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "AIzaSyA8LT6sW1iS8UQWTR4soGezRD2uHfKQhE4",
  authDomain: "tl-store-application.firebaseapp.com",
  projectId: 'tl-store-application',
  storageBucket: 'tl-store-application.firebasestorage.app',
  messagingSenderId: '1068160277012',
  appId: "1:1068160277012:web:20fd2e58c0ce62a60fe501"
});

firebase.messaging();
