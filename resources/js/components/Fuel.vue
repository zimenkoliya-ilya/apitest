<template>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="form-group row">
                    <label for="" class="col-md-2 control-label text-left">TEST API</label>
                    <button class="btn btn-danger col-md-4" @click="testAPI()">
                        TEST API
                    </button>
										<button class="btn btn-danger col-md-4 offset-md-2" @click="testAPI2()">
                        TEST API2
                    </button>
                </div>
            </div>
        </div>
        <hr>
        <!-- query result -->
        <div class="row response">
            <div class="col-12">
                <label class="d-block">Response</label>
                <div class="message">
                  <p>{{ message }}</p>
                </div>
                <label class="d-block">Query</label>
                <div class="query">
                  <p>{{ query }}</p>
                </div>
                <label class="d-block">Result</label>
                <div class="result">
                  <p>{{ result }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
  export default {
      data() {
          return {
              message: "",
              query: "",
              result: "",
              case_id_for_all: 1,
          };
      },
      mounted() {
          console.log("Component mounted.");
          //this.getRecentWords();
      },
      methods: {
          testAPI() {
              axios
                .get("/api/test-api")
                .then(response => {
                    console.log(response.data);
                    let res = response.data;
                    if (res.status == "success") {
                        this.message = res.message;
                        this.query = res.query;
                        this.result = res.data;
                    }
                });
          },
          testAPI2() {
              if (this.case_id_for_all) {
                  axios
                      .get("/api/find-all-case", {
                        params : {
                          case_id: this.case_id_for_all
                        }
                      })
                      .then(response => {
                          console.log(response.data);
                          let res = response.data;
                          if (res.status == "success") {
                              this.message = res.message;
                              this.query = res.query;
                              this.result = res.data;
                          }
                      });
              }
          },
      }
  };
</script>
<style scoped>
  .query, .result {
    width: 100%;
    min-height: 100px;
    padding: 10px;
    color: black;
    background: white;
    border-radius: 4px;
    overflow-y: scroll;
  }
  .query {
    margin-bottom: 20px;
  }
</style>
