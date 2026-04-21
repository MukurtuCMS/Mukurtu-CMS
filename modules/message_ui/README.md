![Build Status](https://travis-ci.org/RoySegall/message_ui.svg?branch=8.x-1.x)

# Message UI

This module provide the UI functionality to the Message(and Message notify 
module).

## Features
* Create messages - Instead of creating the messages via code, which is a 
hustle, you can create the message through a nice UI.
* Updating tokens - When updating a message with tokens, the value of the token 
might not be relevant anymore. The module will take care of that and update for 
you the message tokens and give an updated message instance.
* Delete messages - When developing a site we tend to create messages that have
some white noise in the background. The module provides a nice UI to delete all 
the messages.
* Message notify integration - Message notify provide the option to send the 
message through email(by default). When enabling the message to notify UI 
module, the message view page will have an extra tab "Notify." This tab holds 
the option to send the message object through email.
