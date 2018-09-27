// assets/js/Components/ColumnGraph.js
import React from 'react';
import ReactChartkick, { ColumnChart } from 'react-chartkick'
import Chart from 'chart.js'

ReactChartkick.addAdapter(Chart)

const ColumnGraph = ({data}) => (
  <ColumnChart data={data} />
);

export default ColumnGraph;
