document.addEventListener("DOMContentLoaded", function(event) {
if (!window.Clipboard) {
   var pasteCatcher = document.createElement("div");

   // Firefox allows images to be pasted into contenteditable elements
   pasteCatcher.setAttribute("contenteditable", "");

   // We can hide the element and append it to the body,
   document.body.appendChild(pasteCatcher);

   // as long as we make sure it is always in focus
   pasteCatcher.focus();
   pasteCatcher.style.opacity = '0';
   document.addEventListener("click", function() { pasteCatcher.focus(); });
}
// Add the paste event listener
window.addEventListener("paste", pasteHandler);

/* Handle paste events */
function pasteHandler(e) {
   // We need to check if event.clipboardData is supported (Chrome)
   if (e.clipboardData) {
      // Get the items from the clipboard
      var items = e.clipboardData.items;
      if (items) {
         // Loop through all items, looking for any kind of image
         for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf("image") !== -1) {
               // We need to represent the image as a file,
               var blob = items[i].getAsFile();
               // and use a URL or webkitURL (whichever is available to the browser)
               // to create a temporary URL to the object
               var URLObj = window.URL || window.webkitURL;
               var source = URLObj.createObjectURL(blob);
               console.log(source);
               // The URL can then be used as the source of an image
               createImage(source);
            }
         }
      }
   // If we can't handle clipboard data directly (Firefox),
   // we need to read what was pasted from the contenteditable element
   } else {
      // This is a cheap trick to make sure we read the data
      // AFTER it has been inserted.
      setTimeout(checkInput, 1);
   }
}

/* Parse the input in the paste catcher element */
function checkInput() {
   // Store the pasted content in a variable
   var child = pasteCatcher.childNodes[0];

   // Clear the inner html to make sure we're always
   // getting the latest inserted content
   pasteCatcher.innerHTML = "";

   if (child) {
      // If the user pastes an image, the src attribute
      // will represent the image as a base64 encoded string.
      if (child.tagName === "IMG") {
         createImage(child.src);
      }
   }
}

/* Creates a new image from a given source */
function createImage(source) {
   var pastedImage = new Image();
   pastedImage.onload = function () {
       addPasteToCanvas(pastedImage);
   };
   pastedImage.src = source;
}

function addPasteToCanvas (pastedImage) {
    var canvas = document.createElement("canvas");
    var context;

    canvas.id     = 'isfgallery-canvas';
    canvas.width  = pastedImage.width;
    canvas.height = pastedImage.height;
    document.body.appendChild(canvas);

    context = canvas.getContext('2d');
    context.drawImage(pastedImage, 0, 0);

    // get the image data to manipulate
    var input = context.getImageData(0, 0, canvas.width, canvas.height);

    // get an empty slate to put the data into
    var edge   = context.createImageData(canvas.width, canvas.height);
    var output = context.createImageData(canvas.width, canvas.height);

    // alias some variables for convenience
    // notice that we are using input.width and input.height here
    // as they might not be the same as canvas.width and canvas.height
    // (in particular, they might be different on high-res displays)
    var w = input.width, h = input.height;
    var inputData = input.data;
    var edgeData = edge.data;
    var outputData = output.data;


    // edge detection
    for (var y = 1; y < h - 1; y += 1) {
      for (var x = 1; x < w - 1; x += 1) {
        for (var c = 0; c < 3; c += 1) {
          var i = (y*w + x)*4 + c;
          edgeData[i] = 127 + -inputData[i - w*4 - 4] -   inputData[i - w*4] - inputData[i - w*4 + 4] +
                                -inputData[i - 4]       + 8*inputData[i]       - inputData[i + 4] +
                                -inputData[i + w*4 - 4] -   inputData[i + w*4] - inputData[i + w*4 + 4];
        }
        edgeData[(y*w + x)*4 + 3] = 255; // alpha
      }
    }

    var halfX = canvas.width >> 1;

    var matches = [];

    //find columns with most longest vertical edges
    for (var x = halfX; x--;) {
        matches[x] = 0;
        for (var y = 1; y < h - 1; y += 1) {

            var i = (y*w + x)*4;
            if ((edgeData[i - w * 4] & edgeData[i - w * 4 + 1] & edgeData[i - w * 4 + 2]) === 255) {
                outputData[i] = 200;
                outputData[i + 1] = 255;
                outputData[i + 2] = 200;
                outputData[i + 3] = 255;
                matches[x] = matches[x] + 1;
            } else {
                outputData[i] = edgeData[i];
                outputData[i + 1] = edgeData[i + 1];
                outputData[i + 2] = edgeData[i + 2];
                outputData[i + 3] = edgeData[i + 3];
            }

        }
    }

    var sorted = matches.slice(0);
    var sorted = sorted.sort(function (a, b) {return b - a;});

    var leftEdgeCol;

    for (var x = halfX; x--;) {
        if (matches[x] === sorted[0]) {
            leftEdgeCol = x;
            for (var y = 1; y < h - 1; y += 1) {
                var i = (y*w + x)*4;
            }
        }
    }

    var longestRun = 0;
    var currentRun = 0;
    var longestTop = 0;
    var thisPxWhite, abovePxWhite, i;

    //loop from top to bottom
    for (var y = 1; y < h - 1; y += 1) {
        i = (y * w + leftEdgeCol) * 4;
        thisPxWhite = ((edgeData[i] & edgeData[i + 1] & edgeData[i + 2]) === 255);
        abovePxWhite = ((edgeData[i - (w * 4)] & edgeData[i - (w * 4) + 1] & edgeData[i - (w * 4) + 2]) === 255);


        //if above pixel is white and current pixel is white we are on a run
        if (abovePxWhite & thisPxWhite) {
            //increment current run by 1
            currentRun++;

        //else if above pixel is white and this pixel not white
        } else if (abovePxWhite && !thisPxWhite) {

            //if it is the longest run
            if (currentRun > longestRun) {
                //record the length of the longest run
                longestRun = currentRun;

                //note the top of the run
                longestRunTop = y - 1 - longestRun;
            }

        //else if current pixel is white
        } else if (thisPxWhite) {
            //we've alredy checked the above pixel it is the start of a new
            //clear the counter
            currentRun = 1;
        }
        //else if neither are white, continue
    }

    //game windows aspect is 3.5 * 6
    var gameH = Math.round(longestRun / 100) * 100;
    var gameW = (gameH / 7 * 12);

    //relative to gameW
    var drawingX = gameW * 0.49;
    var drawingY = (gameH / 7) * 0.85;
    var drawingWH = (gameH / 7) * 6;
    var gameY = longestRunTop;
    var gameX = leftEdgeCol;

    for (var y = 1; y < h - 1; y += 1) {
        var i = (y*w + (drawingX + leftEdgeCol))*4;
        outputData[i] = 255;
        outputData[i + 1] = 0;
        outputData[i + 2] = 255;
        outputData[i + 3] = 255;
    }

    for (var x = 1; x < w - 1; x += 1) {
        var i = ((gameY + drawingY)*w + x)*4;
        outputData[i] = 255;
        outputData[i + 1] = 0;
        outputData[i + 2] = 255;
        outputData[i + 3] = 128;
    }

    var cropped = context.getImageData((gameX + drawingX) + 1, (gameY + drawingY) - 2, drawingWH, drawingWH);

    // put the image data back after manipulation
    var canvasForCrop = document.createElement('canvas');
    canvasForCrop.id     = 'isfgallery-cropped';
    canvasForCrop.width  = drawingWH;
    canvasForCrop.height = drawingWH;
    document.body.appendChild(canvasForCrop);

    var cropCtx = canvasForCrop.getContext('2d');
    cropCtx.putImageData(cropped, 0, 0);

    document.getElementById('isfgallery-uploadScreen').style.display = 'block';
}
});
