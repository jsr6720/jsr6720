/** 
 * Productivity hack: color code your calendar to tell at a glance where your time is spent
 * Bonus round know immediately if you are observer|participant|leading any particular meeting
 * 
 * Purpose iterates over calendar and color codes based on finiate state engine
 * 
 *  I threw this script together uinsg https://developers.google.com/apps-script/api/quickstart/js Goggles workspace Apps Script toolchain
 *  A more ambitious me thought about porting it to Go, but javascript was good enough and it stuck
 * Docs API https://developers.google.com/calendar
 * 
 * I just didn't like the thought of integrating my calendar with Reclaim AI or Calendly. #tinfoilhat #allthedata
 * 
 * Quite honestly I just can't understand how people work with a wall of one color. 
 * Obligatory https://xkcd.com/1205/ "Is It Worth The Time"... only three more years to go.
 */
function ColorEvents() {

    let today = new Date();
    let future = new Date();
    future.setDate(future.getDate() + 14); // arbitralliy go out 2 weeks
    Logger.log(today + " " + future);
  
    var calendars = CalendarApp.getCalendarsByName("<username@example.com>"); // insert username
  
    // lame for loop style I learned in college, I'm sure github copilot would make me 55% faster with a for(const calendar of calendars)
    for (let i=0; i<calendars.length; i++) {
  
      let calendar = calendars[i];
      Logger.log(calendar.getName()) // <todo> can work with multiple accounts/calendars
      
      // todo filter out events already set a color (?)
      let events = calendar.getEvents(today, future);
      for (let j=0; j<events.length; j++) { // another non es6 for loop :facepalm:
        let e = events[j];
        
        let title = e.getTitle().toLowerCase(); // yea I could
        
        Logger.log(title); // spool

        // the finate state machine. map it to a color to code your calendar
        // its not perfect since early matches might take precdent over others, but in 2 years this has worked out 99% of the time
        // I focused primarily on title since it did the job but the script has access to many more properties
        // https://developers.google.com/apps-script/reference/calendar/calendar-event
        if (title.indexOf('1:1') != -1) { // 1:1 for example
          e.setColor(CalendarApp.EventColor.PALE_GREEN); // https://developers.google.com/apps-script/reference/calendar/event-color
        }
        else if (e.isOwnedByMe() && e.getGuestList().length == 0) { // empty meetings I set for myself, for example Focus time or Work on project x
          e.setColor(CalendarApp.EventColor.GRAY);
        }
        else if (title.indexOf('<team name #1>') != -1) { // team 1
          e.setColor(CalendarApp.EventColor.ORANGE);
        }
        else if (title.indexOf('<team name #2>') != -1) { // team 2
          e.setColor(CalendarApp.EventColor.GREEN);
        }
        else if (title.indexOf('retro') != -1 || title.indexOf('scrum') != -1 || title.indexOf('refinement') != -1) { // chatGPT 3.5 made a mockery of me
            // agile scrum ceremonies #agile #softwaredevelompent
            e.setColor(CalendarApp.EventColor.MAUVE);
        }
        else {
            // can also do some pretty cool stuff like auto add a colleague/boss to any invite while you're out of office
            // e.addGuest(<user@example.com>);
          e.setColor(CalendarApp.EventColor.CYAN) // tells me I have a fall through
        }
      }
    }
  }