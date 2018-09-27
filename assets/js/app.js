import React from 'react';
import ReactDOM from 'react-dom';
import ChipInput from 'material-ui-chip-input'

import ColumnGraph from './Components/ColumnGraph';

class App extends React.Component {
  constructor() {
    super();

    this.state = {
      graphData: []
    };
  }

  componentDidMount() {
  }

  handleChange(keywords) {
    let data = new FormData()
    data.append('keywords', JSON.stringify(keywords)); 
    fetch('/column/graph/api', {
        method: "POST",
        body: data,
      })
      .then(response => response.json())
      .then(data => {
        console.log(data)
        this.setState({
          graphData: data
        });
      });
  }

  render() {
    return (
      <div>
        <ChipInput
          defaultValue={[]}
          placeholder="搜索"
          fullWidth={true}
          onChange={(chips) => this.handleChange(chips)}
        />
        <div>
          <ColumnGraph data={this.state.graphData}></ColumnGraph>
        </div>
      </div>
    );
  }
}

ReactDOM.render(<App />, document.getElementById('root'));
