// auth.js
import * as firebase from "firebase"
import { EventBus } from './eventBus'

export default {

    currentUser: null,
    init () {
        var self = this

        // first time try to fetch user
        var user = firebase.auth().currentUser;

        // try to find user from localstorage
        if(user == null) {
            const authUser = Object.keys(window.localStorage).filter(item => {
              return item.startsWith('firebase:authUser')
            })
            if(authUser.length > 0) {
                user = authUser[0];
            }
        }

        if (user) {
          this.currentUser = user
        } else {
          this.currentUser = null
        }

        // EventBus.$on('user::loggedIn', function(data) { console.log(data); });
        // EventBus.$on('user::signInError', function(data) { console.log(data); });

        // EventBus.$on('user::signUp', function(data) { console.log(data); });
        // EventBus.$on('user::signUpError', function(data) { console.log(data); });

        // EventBus.$on('user::loggedOut', function(data) { console.log(data); });

        // listen for login events
        firebase.auth().onAuthStateChanged(function(user) {
            if (user) {
                // User is signed in.
                self.currentUser = user
                EventBus.$emit('user::signIn', user);
                // console.log(user);
                // var displayName = user.displayName;
                // var email = user.email;
                // var emailVerified = user.emailVerified;
                // var photoURL = user.photoURL;
                // var isAnonymous = user.isAnonymous;
                // var uid = user.uid;
                // var providerData = user.providerData;
                // ...
            } else {
                self.currentUser = null
                EventBus.$emit('user::signOut');
            }
        });
    },

    login (email, password) {
        firebase.auth().signInWithEmailAndPassword(email, password).catch(function(error) {
            EventBus.$emit('user::signInError', error);
        });
    },

    registerUser(email, password) {

        firebase.auth().createUserWithEmailAndPassword(email, password).then(function() {
            // EventBus.$emit('user::signUp');

        }).catch(function(error) {
            EventBus.$emit('user::signUpError', error);
        });
    },

    getToken () {
        return localStorage.token
    },

    logout () {
        this.currentUser = null
        firebase.auth().signOut().then(function() {
            // EventBus.$emit('user::signOut');
            //
        }).catch(function(error) {
            EventBus.$emit('user::signOutError', error);
        });
    },

    isLoggedIn () {

        if(this.currentUser !== null) {
            return true
        }
        else {
            return false
        }
    }
}