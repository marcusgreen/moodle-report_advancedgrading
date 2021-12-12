import Tabulator from 'report_advancedgrading/tabulator';

export const init = () => {
   var table = new Tabulator("#rubric-header", {
      layout:"fitDataFill",
      data:tabledata,
      columns:[
         {}
      title: "Work Info",
            columns:[
               {title:"Firstname", field:"firstname", sorter:"string", width:200},
               {title:"Lastname", field:"lastname", sorter:"string", width:200}
            ],
      ],

   });

   document.getElementById("download-csv").addEventListener("click", function () {
      table.download("csv", "data.csv");
   });

   //trigger download of data.json file
   document.getElementById("download-json").addEventListener("click", function () {
      table.download("json", "data.json");
   });

   //trigger download of data.xlsx file
   document.getElementById("download-xlsx").addEventListener("click", function () {
      table.download("xlsx", "data.xlsx", { sheetName: "My Data" });
   });

   //trigger download of data.pdf file
   document.getElementById("download-pdf").addEventListener("click", function () {
      table.download("pdf", "data.pdf", {
         orientation: "portrait", //set page orientation to portrait
         title: "Example Report", //add title to report
      });
   });

   //trigger download of data.html file
   document.getElementById("download-html").addEventListener("click", function () {
      table.download("html", "data.html", { style: true });
   });
};