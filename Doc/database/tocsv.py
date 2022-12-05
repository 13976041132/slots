# -*- coding:utf-8 –*-
'''
程序用来将excel批量转换为csv文件
指定源路径和目标路径
'''

import sys
import pandas as pd
import numpy as np
import configparser
import os

def excel_to_csv(file, to_file):
	data_xls = pd.read_excel(file, sheet_name = 0, dtype = str, keep_default_na = False, skiprows = [0,2])
	data_xls.to_csv(to_file, index = 0)

def main():
	config = configparser.ConfigParser()
	config.read('config.ini', encoding="utf-8-sig")
	source_dir = config.get('common', 'source_dir')
	source_dir = source_dir + '/' + sys.argv[1] + '/xlsx'
	target_dir = './csv/' + sys.argv[1]
	file_list = [i for i in os.listdir(source_dir)]
	for file in file_list:
		if file[0:1] == '~':
			continue;
		if file[-4:] != 'xlsx' and file[-3:] != 'xls':
			continue;
		print('To csv ' + file)
		excel_file = source_dir + '/' + file
		csv_file = target_dir + '/' + file[:-5] + '.csv'
		excel_to_csv(excel_file, csv_file)

if __name__ == '__main__':
	main()