import React from 'react';
import ReactDOM from 'react-dom';
import TextField from '@material-ui/core/TextField';
import InputAdornment from '@material-ui/core/InputAdornment';
import Search from '@material-ui/icons/Search';

import ColumnGraph from './Components/ColumnGraph';

class App extends React.Component {
  constructor() {
    super();

    this.state = {
      entries: []
    };
  }

  componentDidMount() {
    fetch('/column/graph/api')
      .then(response => response.json())
      .then(entries => {
        this.setState({
          entries
        });
      });
  }

  render() {
    return (
      <div>
       <TextField
          className='search-box'
          id="input-with-icon-textfield"
          placeholder="搜索"
          InputProps={{
            startAdornment: (
              <InputAdornment position="end">
                <Search />
              </InputAdornment>
            ),
          }}
        />
        {/*<ColumnGraph></ColumnGraph>*/}
      </div>
    );
  }
}

ReactDOM.render(<App />, document.getElementById('root'));
